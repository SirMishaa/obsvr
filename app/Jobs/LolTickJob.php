<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Self re-dispatching watch loop. Unique *until processing* so the re-dispatch from handle() is accepted
 * while concurrent dispatches (hourly sync, new subscription) are absorbed when a tick is already pending.
 */
final class LolTickJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    private const int RETRY_DELAY_SECONDS = 60;

    public int $uniqueFor = 1200;

    public function uniqueId(): string
    {
        return 'lol-tick';
    }

    public function handle(LolMatchWatcher $watcher): void
    {
        try {
            $delay = $watcher->tick();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('LoL esports API unavailable, retrying tick', ['error' => $exception->getMessage()]);
            $delay = self::RETRY_DELAY_SECONDS;
        }

        if ($delay !== null) {
            self::dispatch()->delay($delay);
        }
    }
}
