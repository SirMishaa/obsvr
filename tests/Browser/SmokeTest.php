<?php

declare(strict_types=1);

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
