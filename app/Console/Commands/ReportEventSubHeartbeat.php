<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TwitchEvent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Keepsuit\LaravelOpenTelemetry\Facades\Meter;

#[Signature('app:report-event-sub-heartbeat')]
#[Description('Reports seconds since the last received Twitch EventSub notification, for external monitoring of the webhook pipeline health.')]
final class ReportEventSubHeartbeat extends Command
{
    public function handle(): void
    {
        $lastReceivedAt = TwitchEvent::query()->latest('received_at')->first()?->received_at;

        $secondsSinceLastEvent = Date::now()->diffInSeconds($lastReceivedAt ?? Date::now()->subYears(10), absolute: true);

        Meter::gauge(
            name: 'obsvr_eventsub_seconds_since_last_event',
            unit: 's',
            description: 'Seconds elapsed since the last Twitch EventSub notification was received'
        )->record($secondsSinceLastEvent);

        $this->info(sprintf('obsvr_eventsub_seconds_since_last_event=%d', $secondsSinceLastEvent));
    }
}
