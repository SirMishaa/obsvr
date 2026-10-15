<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncLolScheduleJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lol:sync-schedule')]
#[Description('Sync the LoL esports schedule of subscribed leagues and start the match watcher when a match is close.')]
final class SyncLolSchedule extends Command
{
    public function handle(): void
    {
        dispatch_sync(new SyncLolScheduleJob());

        $this->info('LoL schedule synced.');
    }
}
