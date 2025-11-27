<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\TwitchApiResponses;

beforeEach(function () {
    // Use the sync driver for Concurrency in tests to avoid process isolation issues
    config()->set('concurrency.default', 'sync');

    Http::fake([
        // Twitch OAuth endpoints
        'https://id.twitch.tv/oauth2/token*' => Http::response(TwitchApiResponses::appAccessToken()),

        // Twitch API endpoints
        'https://api.twitch.tv/helix/channels/followed*' => Http::response(TwitchApiResponses::followedChannels()),
        'https://api.twitch.tv/helix/streams/followed*' => Http::response(TwitchApiResponses::followedStreams()),
        'https://api.twitch.tv/helix/eventsub/subscriptions*' => Http::response(TwitchApiResponses::eventSubSubscriptions()),
    ]);
});

test('guests cannot access twitch page', function () {
    $this->get(route('twitch'))
        ->assertRedirect(route('login'));
});

test('authenticated users can access twitch page', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'auth_provider' => 'twitch',
        'auth_provider_id' => 'test-provider-id',
        'auth_provider_access_token' => 'test-access-token',
        'auth_provider_refresh_token' => 'test-refresh-token',
        'auth_provider_expires_at' => now()->addHours(16),
    ]);

    $this->actingAs($user)
        ->get(route('twitch'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Twitch')
            ->has('followedStreamers')
            ->has('statusOfFollowedStreamers')
            ->has('favoriteStreamers')
            ->has('subscriptions')
        );
});

test('redirect to the login page when twitch account is not associated', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'auth_provider' => 'twitch',
        'auth_provider_id' => 'test-provider-id',
    ]);

    $this->actingAs($user)
        ->get(route('twitch'))
        ->assertRedirect(route('login'));
});
