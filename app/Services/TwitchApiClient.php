<?php

namespace App\Services;

use App\Data\TwitchFollowedChannelsPaginatedResponse;
use App\Data\TwitchStreamPaginatedResponse;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Uri;
use RuntimeException;

readonly class TwitchApiClient
{
    protected string $clientId;

    protected Uri $baseUrl;

    public function __construct()
    {
        URL::formatRoot(config('services.twitch.base_url'));
        $this->clientId = config('services.twitch.client_id');
        $this->baseUrl = config('services.twitch.base_url') ? Uri::of(config('services.twitch.base_url')) : null;

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
     */
    private function handleResponse(PendingRequest $pendingRequestOrClient, Uri $uri): array
    {
        try {
            $response = $pendingRequestOrClient->get($uri);
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

                throw new RuntimeException($message);
            }

            return $response->json();

        } catch (ConnectionException $connectionException) {
            throw new RuntimeException('Failed to connect to Twitch API: '.$connectionException->getMessage());
        } catch (Exception $exception) {
            throw new RuntimeException($exception->getMessage());
        }
    }
}
