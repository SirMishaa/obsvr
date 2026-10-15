<?php

declare(strict_types=1);

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use App\Enums\LolNotificationType;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\LolMatchNotification;
use App\Models\User;
use Tests\Mocks\LolEsportsResponses;

test('upsert from data keeps string ids and does not reset watch_ended_at', function (): void {
    $data = LolMatchData::fromScheduleEvent(LolEsportsResponses::scheduleEvent(), LolLeague::Worlds);
    LolMatch::upsertFromData([$data]);
    LolMatch::query()->whereKey('115660588583171786')->update(['watch_ended_at' => now()]);

    LolMatch::upsertFromData([LolMatchData::fromScheduleEvent(
        LolEsportsResponses::scheduleEvent(['state' => 'inProgress']),
        LolLeague::Worlds,
    )]);

    $match = LolMatch::query()->findOrFail('115660588583171786');
    expect($match->id)->toBe('115660588583171786')
        ->and($match->state)->toBe('inProgress')
        ->and($match->watch_ended_at)->not->toBeNull()
        ->and($match->title())->toBe('C9 vs KC')
        ->and($match->summary())->toBe('Worlds · Play-Ins · BO5')
        ->and($match->teamCodes())->toBe(['C9', 'KC']);
});

test('subscription team filter', function (string $codes, bool $expected): void {
    $match = LolMatch::factory()->create();
    $subscription = LolLeagueSubscription::factory()->teams(explode(',', $codes))->create();

    expect($subscription->matches($match))->toBe($expected);
})->with([
    'followed team plays' => ['KC', true],
    'no followed team' => ['T1,G2', false],
    'TBD never matches' => ['TBD', false],
]);

test('all mode matches every match of its league only', function (): void {
    $subscription = LolLeagueSubscription::factory()->create(['league' => LolLeague::Worlds]);

    expect($subscription->matches(LolMatch::factory()->create(['league' => LolLeague::Worlds])))->toBeTrue()
        ->and($subscription->matches(LolMatch::factory()->create(['league' => LolLeague::Lck])))->toBeFalse();
});

test('match subscriptions returns matching subscriptions of its league', function (): void {
    $match = LolMatch::factory()->create();
    $all = LolLeagueSubscription::factory()->create();
    LolLeagueSubscription::factory()->teams(['T1'])->create();
    LolLeagueSubscription::factory()->create(['league' => LolLeague::Lec]);

    expect($match->subscriptions()->pluck('id')->all())->toBe([$all->id]);
});

test('watch url and icon depend on costream channel and team mode', function (): void {
    $match = LolMatch::factory()->create();

    $plain = LolLeagueSubscription::factory()->create();
    expect($plain->watchUrl())->toBe('https://lolesports.com/live/worlds')
        ->and($plain->iconFor($match))->toBe(LolLeague::Worlds->image());

    $costream = LolLeagueSubscription::factory()->teams(['KC'])->create(['costream_channel' => 'skyart']);
    expect($costream->watchUrl())->toBe('https://twitch.tv/skyart')
        ->and($costream->watchLabel())->toBe('Watch on skyart')
        ->and($costream->iconFor($match))->toBe('https://static.lolesports.com/teams/kc.png');
});

test('notification claim succeeds only once', function (): void {
    $match = LolMatch::factory()->create();
    $user = User::factory()->create();

    expect(LolMatchNotification::claim($match, $user, LolNotificationType::Reminder))->toBeTrue()
        ->and(LolMatchNotification::claim($match, $user, LolNotificationType::Reminder))->toBeFalse()
        ->and(LolMatchNotification::claim($match, $user, LolNotificationType::Started))->toBeTrue();
});

test('users get a 20 minutes reminder by default', function (): void {
    expect(User::factory()->create()->refresh()->lol_reminder_minutes)->toBe(20);
});
