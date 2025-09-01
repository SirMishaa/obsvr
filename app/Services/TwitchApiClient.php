<?php

namespace App\Services;

use App\Data\TwitchFollowedChannelsPaginatedResponse;
use App\Data\TwitchStreamPaginatedResponse;
use App\Exceptions\TwitchApiException;
use App\Exceptions\TwitchUnauthorizedException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Uri;
use RuntimeException;

readonly class TwitchApiClient
{
    protected string $clientId;

    protected Uri $baseUrl;

    protected Uri $authUrl;

    public function __construct()
    {
        $this->clientId = config('services.twitch.client_id');
        $this->baseUrl = config('services.twitch.base_url') ? Uri::of(config('services.twitch.base_url')) : null;
        $this->authUrl = config('services.twitch.auth_url') ? Uri::of(config('services.twitch.auth_url')) : null;

        if (empty($this->clientId) || empty($this->baseUrl)) {
            throw new RuntimeException('Twitch API client ID or base URL is not configured.');
        }
    }

    /**
     * Retrieves a cached list of followed streamers for a specific user or fetches from the API if not cached.
     *
     * @param  string  $userId  The ID of the user whose followed channels are being retrieved.
     * @param  string  $token  The authentication token used for the API request.
     * @param  int  $ttlFromNow  The time-to-live for the cache in seconds, defaulting to 7 days (604800 seconds).
     * @return TwitchFollowedChannelsPaginatedResponse The paginated response containing followed channels, either from cache or freshly fetched.
     */
    public function getFollowedStreamers(string $userId, string $token, int $ttlFromNow = 604800): TwitchFollowedChannelsPaginatedResponse
    {
        $cacheKey = sprintf('twitch.followed_channels.%s', $userId);

        $ttl = Carbon::now()->addSeconds($ttlFromNow);

        $payload = Cache::remember($cacheKey, $ttl, function () use ($userId, $token) {
            Log::debug('[TwitchAPI] cache MISS for followed_channels', ['userId' => $userId]);

            return $this->fetchAllFollowedStreamers($userId, $token);
            // return $this->fetchAllFollowedStreamers($userId, $token)->data->toArray();
        });

        return TwitchFollowedChannelsPaginatedResponse::from($payload);
    }

    public function getStatusOfFollowedStreamers(string $userId, string $token, int $ttlFromNow = 3600): TwitchStreamPaginatedResponse
    {
        $cacheKey = sprintf('twitch.followed_streams.%s', $userId);
        $ttl = Carbon::now()->addSeconds($ttlFromNow);

        /**
         * @var array{
         *   data: array<int, array{
         *     id: string,
         *     user_id: string,
         *     user_login: string,
         *     user_name: string,
         *     game_id: string,
         *     game_name: string,
         *     type: string,
         *     title: string,
         *     viewer_count: int,
         *     started_at: string,
         *     language: string,
         *     thumbnail_url: string,
         *     tag_ids: array<int, string>,
         *     tags: array<int, string>,
         *     is_mature: bool
         *   }>,
         *   pagination: array{cursor?: string}
         * } $response
         */
        $response = Cache::remember($cacheKey, $ttl, function () use ($userId, $token) {
            Log::debug('[TwitchAPI] cache MISS for followed_streams', ['userId' => $userId]);
            $url = $this->baseUrl
                ->withPath('/helix/streams/followed')
                ->withQuery(['user_id' => $userId]);

            return $this->handleResponse($this->getHttpClient($token), $url);
        });

        // Dispatch a job to send notifications

        return TwitchStreamPaginatedResponse::from($response);
    }

    /**
     * Retrieves the streaming status of a specific user.
     *
     * @param  string  $userId  The ID of the user whose streaming status is being fetched.
     * @param  string  $token  The authentication token used for the API request.
     * @return TwitchStreamPaginatedResponse Returns the paginated response containing the user's streaming data.
     */
    public function getStatusOfStreamer(string $userId, string $token): TwitchStreamPaginatedResponse
    {
        $url = $this->baseUrl
            ->withPath('/helix/streams')
            ->withQuery(['user_id' => $userId]);

        /**
         * @var array{
         *   data: array<int, array{
         *     id: string,
         *     user_id: string,
         *     user_login: string,
         *     user_name: string,
         *     game_id: string,
         *     game_name: string,
         *     type: string,
         *     title: string,
         *     viewer_count: int,
         *     started_at: string,
         *     language: string,
         *     thumbnail_url: string,
         *     tag_ids: array<int, string>,
         *     tags: array<int, string>,
         *     is_mature: bool
         *   }>,
         *   pagination: array{cursor?: string}
         * } $response
         */
        $response = $this->handleResponse($this->getHttpClient($token), $url);

        return TwitchStreamPaginatedResponse::from($response);

    }

    public function createSubscription(string $userId, string $token, string $eventType = 'stream.online'): void
    {
        $url = $this->baseUrl
            ->withPath('/helix/eventsub/subscriptions')
            ->withQuery(['type' => $eventType, 'version' => '1']);

        $response = $this->handleResponse($this->getHttpClient($token), $url, 'POST', [
            'type' => $eventType,
            'version' => '1',
            'transport' => [
                'method' => 'webhook',
                'callback' => config('services.twitch.eventsub_callback_url'),
            ],
        ]);

    }

    /**
     * Fetches all followed streamers for the given user.
     *
     * This method retrieves a complete list of followed streamers for a specified user by
     * iteratively fetching pages of followed streamers until all results are aggregated.
     *
     * @param  string  $userId  The ID of the user whose followed channels are being fetched.
     * @param  string  $token  The authentication token used for the API request.
     */
    private function fetchAllFollowedStreamers(string $userId, string $token): array
    {
        $allItems = [];
        $total = null;
        $cursor = null;

        do {
            $page = $this->fetchFollowedStreamersPage($userId, $token, $cursor);

            if ($total === null) {
                $total = $page->total;
            }

            $allItems = array_merge($allItems, $page->data->toArray());

            $cursor = $page->pagination['cursor'] ?? null;
        } while ($cursor !== null && count($page->data) > 0);

        /*return new TwitchFollowedChannelsPaginatedResponse(
            total: $total ?? count($allItems),
            data: TwitchFollowedChannelsData::collect($allItems),
            pagination: [] // No pagination info in the aggregated response
        );*/

        return [
            'total' => $total ?? count($allItems),
            'data' => $allItems,
            'pagination' => [],
        ];
    }

    /**
     * Fetches a paginated list of followed streamers for a specific user.
     *
     * @param  string  $userId  The ID of the user whose followed channels are being fetched.
     * @param  string  $token  The authentication token used for the API request.
     * @param  string|null  $cursor  Optionally provides a pagination cursor to fetch the next page of results.
     * @return TwitchFollowedChannelsPaginatedResponse Returns the paginated response containing followed channels.
     */
    private function fetchFollowedStreamersPage(string $userId, string $token, ?string $cursor): TwitchFollowedChannelsPaginatedResponse
    {
        $query = ['user_id' => $userId, 'first' => 100];
        if ($cursor !== null) {
            $query['after'] = $cursor;
        }

        $url = $this->baseUrl
            ->withPath('/helix/channels/followed')
            ->withQuery($query);

        /**
         * @var array{
         *     total: int,
         *     data: array<int, array{
         *         broadcaster_id: string,
         *         broadcaster_name: string,
         *         broadcaster_login: string,
         *         followed_at: string
         *     }>,
         *     pagination: array{cursor?: string}
         * } $response
         */
        $response = $this->handleResponse($this->getHttpClient($token), $url);

        return TwitchFollowedChannelsPaginatedResponse::from($response);
    }

    private function getHttpClient(string $token): PendingRequest
    {
        return Http::withHeaders([
            'Client-Id' => $this->clientId,
            'Authorization' => "Bearer {$token}",
        ])->acceptJson();
    }

    /**
     * Handle the response from the Twitch API.
     *
     * @param  PendingRequest  $pendingRequestOrClient  The HTTP client instance
     * @param  Uri  $uri  The URI to send the request to
     * @param  string  $method  The HTTP method to use (GET or POST)
     * @param  array<string, mixed>  $data  Optional data to send with the request
     * @return array<string, mixed> The JSON response as an array
     */
    private function handleResponse(PendingRequest $pendingRequestOrClient, Uri $uri, string $method = 'GET', array $data = []): array
    {
        try {
            $response = match (strtoupper($method)) {
                'POST' => $pendingRequestOrClient->post($uri, $data),
                default => $pendingRequestOrClient->get($uri),
            };

            if ($response->failed()) {
                $message = sprintf(
                    'Failed to fetch data from Twitch API: (%s) %s',
                    $response->status(),
                    $response->body()
                );

                Log::warning($message, [
                    'uri' => $uri,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);

                // Handle response = 401

                if ($response->status() === 401) {
                    throw new TwitchUnauthorizedException('Unauthorized: Invalid or expired token');
                }

                throw new TwitchApiException($message);
            }

            return $response->json();

        } catch (ConnectionException $connectionException) {
            throw new TwitchApiException('Failed to connect to Twitch API: '.$connectionException->getMessage(), previous: $connectionException);
        } catch (Exception $exception) {
            if ($exception instanceof TwitchApiException) {
                throw $exception;
            }
            throw new TwitchApiException($exception->getMessage(), previous: $exception);
        }
    }

    /**
     * Validates an OAuth2 token to ensure it is still active and meets the minimum validity duration.
     *
     * @param  string  $token  The OAuth2 token to be validated.
     * @param  int  $minValidity  The minimum number of seconds the token should remain valid. Default is 120 seconds.
     * @return bool Returns true if the token is valid and meets the minimum validity, false otherwise.
     */
    public function verifyTokenValidity(string $token, int $minValidity = 120): bool
    {
        $url = $this->authUrl->withPath('/oauth2/validate');
        try {
            /**
             * @var array{
             *     client_id: string,
             *     login: string,
             *     user_id: string,
             *     scopes: array<int, string>,
             *     expires_in: int,
             * } $response
             */
            $response = $this->handleResponse($this->getHttpClient($token), $url);
            $expiresIn = $response['expires_in'] ?? 0;
            if ($expiresIn < $minValidity) {
                return false;
            }

            return true;
        } catch (TwitchUnauthorizedException) {
            return false;
        }
    }
}
