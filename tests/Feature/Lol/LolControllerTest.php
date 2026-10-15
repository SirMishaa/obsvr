<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
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
            ->where('leagues.0', [
                'slug' => 'worlds',
                'label' => 'Worlds',
                'image' => LolLeague::Worlds->image(),
                'subscribed' => true,
            ])
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
