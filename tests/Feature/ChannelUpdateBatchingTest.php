<?php

use App\Enums\TwitchSubscriptionStatus;
use App\Http\Middleware\VerifyTwitchEventSubSignatureMiddleware;
use App\Jobs\SendBatchedChannelUpdateNotification;
use App\Models\FavouriteStreamer;
use App\Models\Subscriptions;
use App\Models\User;
use App\Notifications\TwitchChannelUpdateBatchedNotification;
use App\Notifications\TwitchChannelUpdatedNotification;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->withoutMiddleware(VerifyTwitchEventSubSignatureMiddleware::class);
});

function buildChannelUpdatePayload(string $streamerId, string $streamerName): array
{
    return [
        'subscription' => [
            'id' => fake()->uuid(),
            'type' => 'channel.update',
            'version' => '1',
            'status' => 'enabled',
            'cost' => 1,
            'condition' => [
                'broadcaster_user_id' => $streamerId,
            ],
            'created_at' => now()->toIso8601String(),
        ],
        'event' => [
            'broadcaster_user_id' => $streamerId,
            'broadcaster_user_login' => strtolower($streamerName),
            'broadcaster_user_name' => $streamerName,
            'title' => 'New Title',
            'language' => 'en',
            'category_id' => '509658',
            'category_name' => 'Just Chatting',
            'content_classification_labels' => [],
        ],
    ];
}

test('channel.update with no batch_delay sends notification immediately', function () {
    Notification::fake();
    Bus::fake();

    $user = User::factory()->create();
    $favouriteStreamer = FavouriteStreamer::factory()->create(['user_id' => $user->id,
        'streamer_id' => '12345',
        'streamer_name' => 'TestStreamer',
        'subscription_status' => TwitchSubscriptionStatus::ENABLED,
    ]);
    Subscriptions::factory()->for($favouriteStreamer, 'favouriteStreamer')->create([
        'type' => 'channel.update',
        'status' => TwitchSubscriptionStatus::ENABLED,
        'batch_delay' => null,
    ]);

    $payload = buildChannelUpdatePayload('12345', 'TestStreamer');

    $this->postJson('/twitch/eventsub', $payload, [
        'Twitch-Eventsub-Message-Id' => fake()->uuid(),
        'Twitch-Eventsub-Message-Type' => 'notification',
        'Twitch-Eventsub-Message-Timestamp' => now()->toIso8601String(),
        'Twitch-Eventsub-Message-Signature' => 'sha256=test',
    ])->assertNoContent();

    Notification::assertSentTo($user, TwitchChannelUpdatedNotification::class);
    Bus::assertNotDispatched(SendBatchedChannelUpdateNotification::class);
});

test('channel.update with batch_delay dispatches delayed job and caches data', function () {
    Notification::fake();
    Bus::fake();

    $user = User::factory()->create();
    $favouriteStreamer = FavouriteStreamer::factory()->create(['user_id' => $user->id,
        'streamer_id' => '67890',
        'streamer_name' => 'BatchStreamer',
        'subscription_status' => TwitchSubscriptionStatus::ENABLED,
    ]);
    Subscriptions::factory()->for($favouriteStreamer, 'favouriteStreamer')->create([
        'type' => 'channel.update',
        'status' => TwitchSubscriptionStatus::ENABLED,
        'batch_delay' => 120,
    ]);

    $payload = buildChannelUpdatePayload('67890', 'BatchStreamer');

    $this->postJson('/twitch/eventsub', $payload, [
        'Twitch-Eventsub-Message-Id' => fake()->uuid(),
        'Twitch-Eventsub-Message-Type' => 'notification',
        'Twitch-Eventsub-Message-Timestamp' => now()->toIso8601String(),
        'Twitch-Eventsub-Message-Signature' => 'sha256=test',
    ])->assertNoContent();

    Notification::assertNotSentTo($user, TwitchChannelUpdatedNotification::class);
    Bus::assertDispatched(SendBatchedChannelUpdateNotification::class, function ($job) use ($favouriteStreamer) {
        return $job->favouriteStreamerId === $favouriteStreamer->id;
    });

    $cached = Cache::get("channel_update_batch:{$favouriteStreamer->id}");
    expect($cached)->toBeArray()->toHaveCount(1);
});

test('batched job sends notification with accumulated updates', function () {
    Notification::fake();

    $user = User::factory()->create();
    $favouriteStreamer = FavouriteStreamer::factory()->create(['user_id' => $user->id,
        'streamer_id' => '11111',
        'streamer_name' => 'JobStreamer',
        'subscription_status' => TwitchSubscriptionStatus::ENABLED,
    ]);

    $updates = [
        [
            'broadcaster_user_id' => '11111',
            'broadcaster_user_login' => 'jobstreamer',
            'broadcaster_user_name' => 'JobStreamer',
            'title' => 'First Title',
            'language' => 'en',
            'category_id' => '1',
            'category_name' => 'Gaming',
            'content_classification_labels' => [],
        ],
        [
            'broadcaster_user_id' => '11111',
            'broadcaster_user_login' => 'jobstreamer',
            'broadcaster_user_name' => 'JobStreamer',
            'title' => 'Second Title',
            'language' => 'en',
            'category_id' => '2',
            'category_name' => 'Just Chatting',
            'content_classification_labels' => [],
        ],
    ];

    Cache::put("channel_update_batch:{$favouriteStreamer->id}", $updates, 300);

    $job = new SendBatchedChannelUpdateNotification($favouriteStreamer->id);
    $job->handle();

    Notification::assertSentTo($user, TwitchChannelUpdateBatchedNotification::class, function ($notification) {
        return count($notification->updates) === 2
            && $notification->updates[1]->title === 'Second Title';
    });

    expect(Cache::get("channel_update_batch:{$favouriteStreamer->id}"))->toBeNull();
});

test('subscription batch_delay can be updated via settings', function () {
    $user = User::factory()->create();
    $favouriteStreamer = FavouriteStreamer::factory()->create(['user_id' => $user->id,
        'streamer_id' => '99999',
        'streamer_name' => 'SettingsStreamer',
        'subscription_status' => TwitchSubscriptionStatus::ENABLED,
    ]);
    $subscription = Subscriptions::factory()->for($favouriteStreamer, 'favouriteStreamer')->create([
        'type' => 'channel.update',
        'status' => TwitchSubscriptionStatus::ENABLED,
        'batch_delay' => null,
    ]);

    $this->mock(\App\Services\TwitchTokenManagerService::class)
        ->shouldReceive('ensureFreshAppAccessToken')
        ->andReturn('fake-token');

    Http::fake([
        'api.twitch.tv/helix/eventsub/subscriptions*' => Http::response([
            'total' => 1,
            'total_cost' => 1,
            'max_total_cost' => 10,
            'data' => [
                [
                    'id' => fake()->uuid(),
                    'type' => 'channel.update',
                    'version' => '1',
                    'status' => 'enabled',
                    'cost' => 1,
                    'condition' => ['broadcaster_user_id' => '99999'],
                    'created_at' => now()->toIso8601String(),
                    'transport' => ['method' => 'webhook', 'callback' => 'https://example.com'],
                ],
            ],
            'pagination' => [],
        ]),
    ]);

    $response = $this->actingAs($user)
        ->post(route('subscriptions.update', $favouriteStreamer), [
            'types' => ['channel.update'],
            'batch_delay' => 300,
        ]);

    $response->assertRedirect();

    $subscription->refresh();
    expect($subscription->batch_delay)->toBe(300);
});
