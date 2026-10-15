<?php

declare(strict_types=1);

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;
use App\Notifications\LolMatchReminderNotification;
use App\Notifications\LolMatchStartedNotification;
use App\Services\LolMatchWatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Mocks\LolEsportsResponses;

function createWorldsMatch(): LolMatch
{
    return LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => LolEsportsResponses::MATCH_ID]);
}

beforeEach(function (): void {
    Notification::fake();
});

test('returns null when no match is within the watch window', function (): void {
    $this->travelTo('2026-10-15 15:00:00');
    createWorldsMatch();
    Http::fake();

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull();
    Http::assertNothingSent();
});

test('sends reminder once when within the user offset', function (): void {
    $this->travelTo('2026-10-15 17:41:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    $watcher = resolve(LolMatchWatcher::class);
    $watcher->tick();
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchReminderNotification::class, 1);
    Notification::assertSentTo($subscription->user, LolMatchReminderNotification::class,
        fn (LolMatchReminderNotification $notification): bool => $notification->minutesLeft === 19);
});

test('late subscriber gets reminder with real minutes left', function (): void {
    $this->travelTo('2026-10-15 17:55:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertSentTo($subscription->user, LolMatchReminderNotification::class,
        fn (LolMatchReminderNotification $notification): bool => $notification->minutesLeft === 5);
});

test('no reminder when user disabled it or less than a minute remains', function (): void {
    createWorldsMatch();
    $disabled = LolLeagueSubscription::factory()->for(User::factory()->state(['lol_reminder_minutes' => null]))->create();
    $this->travelTo('2026-10-15 17:59:30');
    $late = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertNotSentTo($disabled->user, LolMatchReminderNotification::class);
    Notification::assertNotSentTo($late->user, LolMatchReminderNotification::class);
});

test('next tick is scheduled at the earliest due moment, clamped to 15 min', function (): void {
    createWorldsMatch();
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();
    $watcher = resolve(LolMatchWatcher::class);

    $this->travelTo('2026-10-15 16:31:00');
    expect($watcher->tick())->toBe(900);

    $this->travelTo('2026-10-15 17:35:00');
    expect($watcher->tick())->toBe(300);

    $this->travelTo('2026-10-15 17:57:00');
    expect($watcher->tick())->toBe(60);
});

test('sends started notification once when live and ends the watch', function (): void {
    $this->travelTo('2026-10-15 18:03:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake(liveIds: [LolEsportsResponses::MATCH_ID]);

    $watcher = resolve(LolMatchWatcher::class);
    expect($watcher->tick())->toBeNull();
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchStartedNotification::class, 1);
    Notification::assertNotSentTo($subscription->user, LolMatchReminderNotification::class);
    expect(LolMatch::query()->findOrFail(LolEsportsResponses::MATCH_ID)->watch_ended_at)->not->toBeNull();
});

test('postponed match keeps polling and does not resend the reminder', function (): void {
    $this->travelTo('2026-10-15 17:45:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();
    $watcher = resolve(LolMatchWatcher::class);
    $watcher->tick();

    $this->travelTo('2026-10-15 18:05:00');
    LolEsportsResponses::fake(startTime: '2026-10-15T18:30:00Z');
    expect($watcher->tick())->toBe(300);

    $this->travelTo('2026-10-15 18:10:00');
    $watcher->tick();

    $this->travelTo('2026-10-15 18:31:00');
    LolEsportsResponses::fake(state: 'inProgress', startTime: '2026-10-15T18:30:00Z');
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchReminderNotification::class, 1);
    Notification::assertSentToTimes($subscription->user, LolMatchStartedNotification::class, 1);
});

test('completed match ends the watch without notification', function (): void {
    $this->travelTo('2026-10-15 19:00:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake(state: 'completed');

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull();
    Notification::assertNothingSentTo($subscription->user);
});

test('abandons a match never seen live 4 hours after its start', function (): void {
    $this->travelTo('2026-10-15 22:01:00');
    createWorldsMatch();
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull()
        ->and(LolMatch::query()->findOrFail(LolEsportsResponses::MATCH_ID)->watch_ended_at)->not->toBeNull();
});

test('team mode subscriber is only notified for followed teams', function (): void {
    $this->travelTo('2026-10-15 18:03:00');
    createWorldsMatch();
    $kcFan = LolLeagueSubscription::factory()->teams(['KC'])->create();
    $t1Fan = LolLeagueSubscription::factory()->teams(['T1'])->create();
    LolEsportsResponses::fake(liveIds: [LolEsportsResponses::MATCH_ID]);

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertSentTo($kcFan->user, LolMatchStartedNotification::class);
    Notification::assertNotSentTo($t1Fan->user, LolMatchStartedNotification::class);
});
