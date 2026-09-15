<?php

use App\Models\TwitchEvent;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Date::setTestNow('2026-09-15 12:00:00');
});

afterEach(function (): void {
    Date::setTestNow();
});

test('reports a large gap when no event was ever received', function (): void {
    $this->artisan('app:report-event-sub-heartbeat')
        ->assertSuccessful()
        ->expectsOutputToContain('obsvr_eventsub_seconds_since_last_event='.now()->diffInSeconds(now()->subYears(10), absolute: true));
});

test('reports seconds elapsed since the last received event', function (): void {
    TwitchEvent::factory()->create(['received_at' => now()->subMinutes(5)]);

    $this->artisan('app:report-event-sub-heartbeat')
        ->assertSuccessful()
        ->expectsOutputToContain('obsvr_eventsub_seconds_since_last_event=300');
});

test('uses the most recent event when several exist', function (): void {
    TwitchEvent::factory()->create(['received_at' => now()->subHours(2)]);
    TwitchEvent::factory()->create(['received_at' => now()->subMinutes(1)]);

    $this->artisan('app:report-event-sub-heartbeat')
        ->assertSuccessful()
        ->expectsOutputToContain('obsvr_eventsub_seconds_since_last_event=60');
});
