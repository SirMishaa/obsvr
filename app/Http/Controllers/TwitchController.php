<?php

namespace App\Http\Controllers;

use App\Models\FavouriteStreamer;
use App\Services\TwitchApiClient;
use App\Services\TwitchTokenManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class TwitchController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $user = auth()->user();

        $providerId = $user->auth_provider_id;
        $accessToken = $user->auth_provider_access_token;

        try {
            app(TwitchTokenManagerService::class)->ensureFreshUserAccessTokens($user);
        } catch (\Throwable $e) {
            auth()->logout();

            return redirect()->route('login');
        }

        /**
         * This way since we had weirds issues with unserializable objects when using Concurrency::run
         * when capturing objects and not using static closures.
         */
        [$statusOfFollowedStreamers, $followedStreamers, $favoriteStreamers] = Concurrency::run([
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
        ]);

        return Inertia::render('Twitch', [
            'redirect' => route('socialite.redirect', ['provider' => 'twitch']),
            'followedStreamers' => $followedStreamers,
            'statusOfFollowedStreamers' => $statusOfFollowedStreamers,
            'favoriteStreamers' => $favoriteStreamers,
        ]);
    }

    /**
     * Set the streamer as a favourite or remove from favourites.
     */
    public function toggleFavoriteStreamer(string $streamerId): RedirectResponse
    {
        $userId = auth()->id();
        $favorite = FavouriteStreamer::firstOrCreate(
            [
                'user_id' => $userId,
                'streamer_id' => $streamerId,
            ],
            [
                'streamer_name' => 'Unknown',
            ]
        );

        if (! $favorite->wasRecentlyCreated) {
            $favorite->delete();
        }

        return back(status: 303);

    }
}
