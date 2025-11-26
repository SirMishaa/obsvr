<?php

use App\Http\Middleware\VerifyTwitchEventSubSignatureMiddleware;
use App\Models\TwitchEvent;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-11-16 13:00:00');
    $this->withoutMiddleware(VerifyTwitchEventSubSignatureMiddleware::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('stream.online event is stored in database', function () {
    $payload = buildTwitchWebhookPayload('stream.online', [
        'id' => 'stream-123',
        'broadcaster_user_id' => '123456',
        'broadcaster_user_login' => 'teststreamer',
        'broadcaster_user_name' => 'TestStreamer',
        'type' => 'live',
        'started_at' => '2025-11-16T13:00:00Z',
    ]);

    $response = $this->postJson('/twitch/eventsub', $payload, [
        'Twitch-Eventsub-Message-Id' => 'msg-123',
        'Twitch-Eventsub-Message-Type' => 'notification',
        'Twitch-Eventsub-Message-Timestamp' => '2025-11-16T13:00:00Z',
        'Twitch-Eventsub-Message-Signature' => 'sha256=test',
    ]);

    $response->assertNoContent();

    expect(TwitchEvent::count())->toBe(1);

    $event = TwitchEvent::first();
    expect($event->event_id)->toBe('stream-123');
    expect($event->event_type)->toBe('stream.online');
    expect($event->streamer_id)->toBe('123456');
    expect($event->streamer_name)->toBe('TestStreamer');
    expect($event->payload)->toBeArray();
    expect($event->occurred_at->toDateTimeString())->toBe('2025-11-16 13:00:00');
    expect($event->received_at)->not->toBeNull();
});

test('stream.offline event is stored in database', function () {
    $payload = buildTwitchWebhookPayload('stream.offline', [
        'id' => 'stream-456',
        'broadcaster_user_id' => '789012',
        'broadcaster_user_login' => 'anotherstreamer',
        'broadcaster_user_name' => 'AnotherStreamer',
    ]);

    $response = $this->postJson('/twitch/eventsub', $payload, [
        'Twitch-Eventsub-Message-Id' => 'msg-456',
        'Twitch-Eventsub-Message-Type' => 'notification',
        'Twitch-Eventsub-Message-Timestamp' => '2025-11-16T14:00:00Z',
        'Twitch-Eventsub-Message-Signature' => 'sha256=test',
    ]);

    $response->assertNoContent();

    expect(TwitchEvent::count())->toBe(1);

    $event = TwitchEvent::first();
    expect($event->event_id)->toBe('stream-456');
    expect($event->event_type)->toBe('stream.offline');
    expect($event->streamer_id)->toBe('789012');
    expect($event->streamer_name)->toBe('AnotherStreamer');
});

test('channel.update event is stored in database', function () {
    $payload = buildTwitchWebhookPayload('channel.update', [
        'id' => 'update-789',
        'broadcaster_user_id' => '345678',
        'broadcaster_user_login' => 'updatestreamer',
        'broadcaster_user_name' => 'UpdateStreamer',
        'title' => 'New Stream Title',
        'language' => 'en',
        'category_id' => '509658',
        'category_name' => 'Just Chatting',
        'content_classification_labels' => [],
    ]);

    $response = $this->postJson('/twitch/eventsub', $payload, [
        'Twitch-Eventsub-Message-Id' => 'msg-789',
        'Twitch-Eventsub-Message-Type' => 'notification',
        'Twitch-Eventsub-Message-Timestamp' => '2025-11-16T15:00:00Z',
        'Twitch-Eventsub-Message-Signature' => 'sha256=test',
    ]);

    $response->assertNoContent();

    expect(TwitchEvent::count())->toBe(1);

    $event = TwitchEvent::first();
    expect($event->event_id)->toBe('update-789');
    expect($event->event_type)->toBe('channel.update');
    expect($event->streamer_id)->toBe('345678');
    expect($event->streamer_name)->toBe('UpdateStreamer');
    expect($event->payload['title'])->toBe('New Stream Title');
    expect($event->payload['category_name'])->toBe('Just Chatting');
});

test('multiple events are stored separately', function () {
    $payloads = [
        buildTwitchWebhookPayload('stream.online', [
            'id' => 'event-1',
            'broadcaster_user_id' => '111',
            'broadcaster_user_login' => 'streamer1',
            'broadcaster_user_name' => 'Streamer1',
            'type' => 'live',
            'started_at' => '2025-11-16T10:00:00Z',
        ]),
        buildTwitchWebhookPayload('channel.update', [
            'id' => 'event-2',
            'broadcaster_user_id' => '111',
            'broadcaster_user_login' => 'streamer1',
            'broadcaster_user_name' => 'Streamer1',
            'title' => 'Updated Title',
            'language' => 'en',
            'category_id' => '12345',
            'category_name' => 'Gaming',
            'content_classification_labels' => [],
        ]),
        buildTwitchWebhookPayload('stream.offline', [
            'id' => 'event-3',
            'broadcaster_user_id' => '111',
            'broadcaster_user_login' => 'streamer1',
            'broadcaster_user_name' => 'Streamer1',
        ]),
    ];

    foreach ($payloads as $payload) {
        $response = $this->postJson('/twitch/eventsub', $payload, [
            'Twitch-Eventsub-Message-Id' => fake()->uuid(),
            'Twitch-Eventsub-Message-Type' => 'notification',
            'Twitch-Eventsub-Message-Timestamp' => now()->toIso8601String(),
            'Twitch-Eventsub-Message-Signature' => 'sha256=test',
        ]);
        $response->assertNoContent();
    }

    expect(TwitchEvent::count())->toBe(3);
    expect(TwitchEvent::where('event_type', 'stream.online')->count())->toBe(1);
    expect(TwitchEvent::where('event_type', 'channel.update')->count())->toBe(1);
    expect(TwitchEvent::where('event_type', 'stream.offline')->count())->toBe(1);
});

/**
 * @param  array<string, mixed>  $eventData
 * @return array<string, mixed>
 */
function buildTwitchWebhookPayload(string $eventType, array $eventData): array
{
    return [
        'subscription' => [
            'id' => fake()->uuid(),
            'type' => $eventType,
            'version' => '1',
            'status' => 'enabled',
            'cost' => 1,
            'condition' => [
                'broadcaster_user_id' => $eventData['broadcaster_user_id'] ?? fake()->numerify('########'),
            ],
            'created_at' => now()->toIso8601String(),
        ],
        'event' => $eventData,
    ];
}
