<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Jobs\LolTickJob;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Services\LolEsportsClient;
use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\LolEsportsResponses;

test('sync fetches only subscribed leagues, stores matches and starts the tick', function (): void {
    $this->travelTo('2026-10-15 17:00:00');
    Queue::fake();
    LolLeagueSubscription::factory()->create(['league' => LolLeague::Worlds]);
    Http::fake(['*/getSchedule*' => Http::response(LolEsportsResponses::schedule([LolEsportsResponses::scheduleEvent()]))]);

    new SyncLolScheduleJob()->handle(resolve(LolEsportsClient::class));

    expect(LolMatch::query()->count())->toBe(1);
    Http::assertSentCount(1);
    Queue::assertPushed(LolTickJob::class);
});

test('sync does not start the tick when no match is close', function (): void {
    $this->travelTo('2026-10-15 10:00:00');
    Queue::fake();
    LolLeagueSubscription::factory()->create();
    Http::fake(['*/getSchedule*' => Http::response(LolEsportsResponses::schedule([LolEsportsResponses::scheduleEvent()]))]);

    new SyncLolScheduleJob()->handle(resolve(LolEsportsClient::class));

    Queue::assertNotPushed(LolTickJob::class);
});

test('sync without subscriptions calls nothing', function (): void {
    Queue::fake();
    Http::fake();

    expect(Artisan::call('lol:sync-schedule'))->toBe(0);

    Http::assertNothingSent();
});

test('tick re-dispatches itself with the watcher delay', function (): void {
    $this->travelTo('2026-10-15 17:35:00');
    Queue::fake();
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => LolEsportsResponses::MATCH_ID]);
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertPushed(LolTickJob::class, fn (LolTickJob $job): bool => $job->delay === 300);
});

test('tick stops when there is nothing to watch', function (): void {
    Queue::fake();
    Http::fake();

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertNothingPushed();
});

test('tick survives api errors by retrying in 60 seconds', function (): void {
    $this->travelTo('2026-10-15 17:35:00');
    Queue::fake();
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create();
    Http::fake(['*' => Http::failedConnection()]);

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertPushed(LolTickJob::class, fn (LolTickJob $job): bool => $job->delay === 60);
});

test('tick is unique until processing', function (): void {
    expect(class_implements(LolTickJob::class))->toHaveKey(ShouldBeUniqueUntilProcessing::class)
        ->and(new LolTickJob()->uniqueId())->toBe('lol-tick');
});

test('tick survives unexpected errors by retrying in 60 seconds', function (): void {
    $this->travelTo('2026-10-15 17:35:00');
    Queue::fake();
    Exceptions::fake();
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create();
    Http::fake(['*' => fn () => throw new RuntimeException('boom')]);

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertPushed(LolTickJob::class, fn (LolTickJob $job): bool => $job->delay === 60);
    Exceptions::assertReported(RuntimeException::class);
});
