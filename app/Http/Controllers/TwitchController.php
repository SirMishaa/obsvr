<?php

namespace App\Http\Controllers;

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
        [$statusOfFollowedStreamers, $followedStreamers] = Concurrency::run([
            static fn () => app(\App\Services\TwitchApiClient::class)
                ->getStatusOfFollowedStreamers($providerId, $accessToken, 120)
                ->data
                ->toArray(),
            static fn () => app(\App\Services\TwitchApiClient::class)
                ->getFollowedStreamers($providerId, $accessToken)
                ->data
                ->toArray(),
        ]);

        return Inertia::render('Twitch', [
            'redirect' => route('socialite.redirect', ['provider' => 'twitch']),
            'followedStreamers' => $followedStreamers,
            'statusOfFollowedStreamers' => $statusOfFollowedStreamers,
            'favoriteStreamers' => ['496523436', '27115917', '50795214', '48099992'],
        ]);
    }
}
