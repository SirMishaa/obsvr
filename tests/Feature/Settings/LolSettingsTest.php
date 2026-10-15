<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('lol settings page shows the reminder delay', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('lol-settings.edit'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('settings/Lol')
            ->where('reminderMinutes', 20)
            ->where('reminderOptions', [5, 10, 15, 20, 30, 60]));
});

test('reminder delay can be changed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => 30])
        ->assertRedirect();

    expect($user->refresh()->lol_reminder_minutes)->toBe(30);
});

test('reminder can be turned off', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => null])
        ->assertRedirect();

    expect($user->refresh()->lol_reminder_minutes)->toBeNull();
});

test('reminder delay must be one of the options', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => 7])
        ->assertSessionHasErrors('reminder_minutes');

    expect($user->refresh()->lol_reminder_minutes)->toBe(20);
});

test('guests cannot see lol settings', function (): void {
    $this->get(route('lol-settings.edit'))->assertRedirect(route('login'));
});
