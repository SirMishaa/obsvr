<?php

declare(strict_types=1);

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Notifications\LolMatchReminderNotification;
use App\Notifications\LolMatchStartedNotification;

test('reminder push shows real minutes left, local start time and watch link', function (): void {
    $match = LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create();
    $subscription = LolLeagueSubscription::factory()->create(['costream_channel' => 'skyart']);

    $notification = new LolMatchReminderNotification($match, $subscription, 7);
    $message = $notification->toWebPush($subscription->user, $notification)->toArray();

    expect($message['title'])->toBe('⏰ C9 vs KC in 7 min')
        ->and($message['body'])->toBe('Worlds · Play-Ins · BO5 · 20:00')
        ->and($message['icon'])->toBe('https://static.lolesports.com/leagues/1592594612171_WorldsDarkBG.png')
        ->and($message['data'])->toBe(['url' => 'https://twitch.tv/skyart'])
        ->and($message['actions'][0])->toMatchArray(['title' => 'Watch on skyart', 'action' => 'https://twitch.tv/skyart']);
});

test('started push links to lolesports without costream', function (): void {
    $match = LolMatch::factory()->create();
    $subscription = LolLeagueSubscription::factory()->create();

    $notification = new LolMatchStartedNotification($match, $subscription);
    $message = $notification->toWebPush($subscription->user, $notification)->toArray();

    expect($message['title'])->toBe('🔴 C9 vs KC has started')
        ->and($message['body'])->toBe('Worlds · Play-Ins · BO5')
        ->and($message['data'])->toBe(['url' => 'https://lolesports.com/live/worlds']);
});
