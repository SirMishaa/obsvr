<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PushTestNotification;
use App\Notifications\TwitchStreamerStreamStartedNotification;
use Illuminate\Support\Facades\Notification;

test('guests cannot send a test push', function (): void {
    $this->post(route('push.test'))
        ->assertRedirect(route('login'));
});

test('a test push is sent to the authenticated user', function (): void {
    Notification::fake();
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->post(route('push.test'))
        ->assertStatus(303);

    Notification::assertSentTo($user, PushTestNotification::class);
});

test('saving a subscription replaces the previous endpoint', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->updatePushSubscription('https://push.example/old', 'old-key', 'old-token');

    $this->actingAs($user)
        ->post(route('push.subscription'), [
            'endpoint' => 'https://push.example/new',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-token'],
            'previousEndpoint' => 'https://push.example/old',
        ])
        ->assertStatus(303);

    expect($user->pushSubscriptions()->pluck('endpoint')->all())
        ->toBe(['https://push.example/new']);
});

test('a previous endpoint of another user is left untouched', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $otherUser = User::factory()->create();
    $otherUser->updatePushSubscription('https://push.example/other', 'key', 'token');

    $this->actingAs($user)
        ->post(route('push.subscription'), [
            'endpoint' => 'https://push.example/new',
            'keys' => ['p256dh' => 'new-key', 'auth' => 'new-token'],
            'previousEndpoint' => 'https://push.example/other',
        ])
        ->assertStatus(303);

    expect($otherUser->pushSubscriptions()->count())->toBe(1);
});

test('pushes are sent with high urgency so sleeping devices deliver them right away', function (): void {
    $user = User::factory()->create();

    foreach ([new TwitchStreamerStreamStartedNotification('Sardoche'), new PushTestNotification] as $notification) {
        expect($notification->toWebPush($user, $notification)->getOptions())
            ->toHaveKey('urgency', 'high')
            ->toHaveKey('TTL');
    }
});
