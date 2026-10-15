<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to login', function (): void {
    $this->get(route('lol'))->assertRedirect(route('login'));
});

test('page lists leagues with subscription state', function (): void {
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->create(['league' => LolLeague::Worlds]);

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Lol')
            ->has('leagues', 5)
            ->where('leagues.0.slug', 'worlds')
            ->where('leagues.0.label', 'Worlds')
            ->where('leagues.0.image', LolLeague::Worlds->image())
            ->where('leagues.0.subscribed', true)
            ->where('leagues.1.subscribed', false));
});

test('toggle subscribes and triggers a schedule sync', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('lol.leagues.toggle', 'worlds'))->assertRedirect();

    expect($user->lolLeagueSubscriptions()->where('league', 'worlds')->exists())->toBeTrue();
    Queue::assertPushed(SyncLolScheduleJob::class);
});

test('toggle unsubscribes when already subscribed', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->create(['league' => LolLeague::Worlds]);

    $this->actingAs($user)->post(route('lol.leagues.toggle', 'worlds'))->assertRedirect();

    expect($user->lolLeagueSubscriptions()->exists())->toBeFalse();
    Queue::assertNotPushed(SyncLolScheduleJob::class);
});

test('unknown league is not found', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/lol/leagues/lpl')
        ->assertNotFound();
});

test('page exposes league settings, available teams and upcoming matches', function (): void {
    $this->travelTo('2026-10-15 12:00:00');
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create(['costream_channel' => 'skyart']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => '1']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-16 18:00:00'))->create([
        'id' => '2',
        'teams' => [
            ['name' => 'T1', 'code' => 'T1', 'image' => 'https://static.lolesports.com/teams/t1.png'],
            ['name' => 'TBD', 'code' => 'TBD', 'image' => ''],
        ],
    ]);
    LolMatch::factory()->startingAt(now()->parse('2026-10-30 18:00:00'))->create(['id' => '3']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => '4', 'league' => LolLeague::Lck]);

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('leagues.0.mode', 'teams')
            ->where('leagues.0.teamCodes', ['KC'])
            ->where('leagues.0.costreamChannel', 'skyart')
            ->where('leagues.0.teams', [
                ['code' => 'C9', 'name' => 'Cloud9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
                ['code' => 'KC', 'name' => 'Karmine Corp', 'image' => 'https://static.lolesports.com/teams/kc.png'],
                ['code' => 'T1', 'name' => 'T1', 'image' => 'https://static.lolesports.com/teams/t1.png'],
            ])
            ->where('leagues.1.teams', [])
            ->has('upcomingMatches', 2)
            ->where('upcomingMatches.0.id', '1')
            ->where('upcomingMatches.0.startTime', '2026-10-15T18:00:00Z')
            ->where('upcomingMatches.0.leagueLabel', 'Worlds')
            ->where('upcomingMatches.0.notified', true)
            ->where('upcomingMatches.1.id', '2')
            ->where('upcomingMatches.1.notified', false));
});

test('unfollowed leagues have no upcoming matches', function (): void {
    $user = User::factory()->create();
    LolMatch::factory()->create();

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('upcomingMatches', 0));
});

test('league settings can switch to followed teams with a co-streamer', function (): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), [
            'mode' => 'teams',
            'team_codes' => ['KC', 'T1'],
            'costream_channel' => 'skyart',
        ])
        ->assertRedirect();

    $subscription->refresh();
    expect($subscription->mode)->toBe(LolSubscriptionMode::Teams)
        ->and($subscription->team_codes)->toBe(['KC', 'T1'])
        ->and($subscription->costream_channel)->toBe('skyart');
});

test('switching back to all clears team codes', function (): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), ['mode' => 'all', 'costream_channel' => null])
        ->assertRedirect();

    expect($subscription->refresh()->team_codes)->toBeNull()
        ->and($subscription->mode)->toBe(LolSubscriptionMode::All);
});

test('league settings validation', function (array $payload, string $errorKey): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), $payload)
        ->assertSessionHasErrors($errorKey);

    expect($subscription->refresh()->mode)->toBe(LolSubscriptionMode::All);
})->with([
    'teams mode without team' => [['mode' => 'teams', 'team_codes' => []], 'team_codes'],
    'unknown mode' => [['mode' => 'favourites'], 'mode'],
    'costream as url' => [['mode' => 'all', 'costream_channel' => 'https://twitch.tv/skyart'], 'costream_channel'],
]);

test('settings of a league that is not followed are not found', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), ['mode' => 'all'])
        ->assertNotFound();

    expect($user->lolLeagueSubscriptions()->exists())->toBeFalse();
});

test('team chips come from recent matches of followed leagues only, latest name winning', function (): void {
    $this->travelTo('2026-10-15 12:00:00');
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->create();
    LolMatch::factory()->startingAt(now()->subDays(90))->create(['teams' => [
        ['name' => 'Old Team', 'code' => 'OLD', 'image' => ''],
        ['name' => 'Karmine Corp Old', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc-old.png'],
    ]]);
    LolMatch::factory()->startingAt(now()->subDays(10))->create(['teams' => [
        ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc.png'],
        ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
    ]]);
    LolMatch::factory()->create(['league' => LolLeague::Lck]);

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('leagues.0.teams', [
                ['code' => 'C9', 'name' => 'Cloud9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
                ['code' => 'KC', 'name' => 'Karmine Corp', 'image' => 'https://static.lolesports.com/teams/kc.png'],
            ])
            ->where('leagues.3.teams', []));
});
