<?php

namespace App\Http\Controllers;

use App\Models\FavouriteStreamer;
use App\Models\TwitchEvent;
use App\Services\TwitchApiClient;
use App\Services\TwitchTokenManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class TwitchController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();

        try {
            $twitchTokenManagerService = app(TwitchTokenManagerService::class);
            $twitchTokenManagerService->ensureFreshUserAccessTokens($user);
            $appToken = $twitchTokenManagerService->ensureFreshAppAccessToken();
        } catch (\Throwable $e) {
            auth()->logout();

            return redirect()->route('login');
        }

        /**
         * We need to refresh the user instance since the tokens might have been updated
         * in the ensureFreshUserAccessTokens method.
         *
         * Todo: Refactor this to a middleware before hydrating the user in the controller.
         */
        $user->refresh();
        $providerId = $user->auth_provider_id;
        $accessToken = $user->auth_provider_access_token;

        /**
         * This way since we had weirds issues with unserializable objects when using Concurrency::run
         * when capturing objects and not using static closures.
         */
        [$statusOfFollowedStreamers, $followedStreamers, $favoriteStreamers, $subscriptions] = Concurrency::run([
            static fn () => app(TwitchApiClient::class)
                ->getStatusOfFollowedStreamers($providerId, $accessToken, 120)
                ->data
                ->toArray(),
            static fn () => app(TwitchApiClient::class)
                ->getFollowedStreamers($providerId, $accessToken)
                ->data
                ->toArray(),
            static fn () => $user->favouriteStreamers()
                ->pluck('streamer_id')
                ->toArray(),
            static fn () => app(TwitchApiClient::class)
                ->fetchSubscriptions(null, $appToken)
                ->data
                ->toArray(),
        ]);

        return Inertia::render('Twitch', [
            'followedStreamers' => $followedStreamers,
            'statusOfFollowedStreamers' => $statusOfFollowedStreamers,
            'favoriteStreamers' => $favoriteStreamers,
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Set the streamer as a favourite or remove from favourites.
     */
    public function toggleFavoriteStreamer(string $streamerId, Request $request): RedirectResponse
    {
        $userId = auth()->id();
        $validated = $request->validate([
            'streamerName' => ['required', 'string'],
        ]);

        $favorite = FavouriteStreamer::firstOrCreate(
            [
                'user_id' => $userId,
                'streamer_id' => $streamerId,
            ],
            [
                'streamer_name' => $validated['streamerName'],
            ]
        );

        if (! $favorite->wasRecentlyCreated) {
            $favorite->delete();
        }

        return back(status: 303);

    }

    /**
     * Get the events for a favourite streamer. We're not using Inertia since it adds 600 ms of overhead.
     */
    public function getStreamerEvents(FavouriteStreamer $favouriteStreamer): JsonResponse
    {
        if ($favouriteStreamer->user_id !== auth()->id()) {
            abort(403);
        }

        $streamerEvents = TwitchEvent::where('streamer_id', $favouriteStreamer->streamer_id)
            ->orderBy('occurred_at', 'desc')
            ->limit(50)
            ->get(['id', 'event_type', 'payload', 'occurred_at']);

        return response()->json($streamerEvents);
    }
}
