<?php

namespace App\Http\Controllers;

use App\Models\FavouriteStreamer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Concurrency;
use Inertia\Inertia;
use Inertia\Response;

class TwitchController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();

        $providerId = (string) $user->auth_provider_id;
        $accessToken = (string) $user->auth_provider_access_token;

        /**
         * This way since we had weirds issues with unserializable objects when using Concurrency::run
         * when capturing objects and not using static closures.
         */
        [$statusOfFollowedStreamers, $followedStreamers, $favoriteStreamers] = Concurrency::run([
            static fn () => app(\App\Services\TwitchApiClient::class)
                ->getStatusOfFollowedStreamers($providerId, $accessToken, 120)
                ->data
                ->toArray(),
            static fn () => app(\App\Services\TwitchApiClient::class)
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
