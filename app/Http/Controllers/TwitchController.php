<?php

namespace App\Http\Controllers;

use App\Services\TwitchApiClient;
use Inertia\Inertia;
use Inertia\Response;

class TwitchController extends Controller
{
    public function index(): Response
    {

        $user = auth()->user();
        $twitchApiClient = app(TwitchApiClient::class);
        $followedStreamers = $twitchApiClient->getFollowedStreamers($user->auth_provider_id, $user->auth_provider_access_token);

        return Inertia::render('Twitch', [
            'redirect' => route('socialite.redirect', ['provider' => 'twitch']),
            'followedStreamers' => $followedStreamers->data->toArray(),
        ]);
    }
}
