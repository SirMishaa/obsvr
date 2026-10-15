<?php

declare(strict_types=1);

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;

it('renders guest pages without javascript errors', function (): void {
    visit(['/', '/login', '/register', '/forgot-password'])
        ->assertNoJavaScriptErrors();
});

it('renders settings pages without javascript errors', function (): void {
    $this->actingAs(User::factory()->create());

    visit(['/settings/profile', '/settings/password', '/settings/appearance'])
        ->assertNoJavaScriptErrors();
});

it('highlights the current settings section', function (): void {
    $this->actingAs(User::factory()->create());

    visit('/settings/password')
        ->assertSee('Update password')
        ->click('Profile')
        ->assertPathIs('/settings/profile')
        ->assertSee('Profile information');
});

it('renders the league of legends pages without javascript errors', function (): void {
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create();
    LolMatch::factory()->create();
    $this->actingAs($user);

    visit(['/lol', '/settings/lol'])
        ->assertNoJavaScriptErrors();
});

it('shows followed league settings and upcoming matches', function (): void {
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create();
    LolMatch::factory()->create();
    $this->actingAs($user);

    visit('/lol')
        ->assertSee('Upcoming matches')
        ->assertSee('My teams')
        ->assertSee('Co-streamer (Twitch)')
        ->assertSee('C9');
});

it('keeps a selected team that is no longer scheduled deselectable', function (): void {
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->teams(['OLD'])->create();
    LolMatch::factory()->create();
    $this->actingAs($user);

    visit('/lol')
        ->assertSee('OLD')
        ->click('OLD')
        ->click('Save')
        ->assertSee('team codes field is required');
});
