<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Services\LolEsportsClient;
use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncLolScheduleJob implements ShouldQueue
{
    use Queueable;

    public function handle(LolEsportsClient $client): void
    {
        $leagues = LolLeagueSubscription::query()->distinct()->pluck('league');
        $homeLeagues = $leagues->isNotEmpty() ? $this->homeLeagues($client) : [];

        foreach ($leagues as $league) {
            /** @var LolLeague $league */
            try {
                LolMatch::upsertFromData(array_map(
                    fn (LolMatchData $match): LolMatchData => $match->withHomeLeagues($homeLeagues),
                    $client->schedule($league),
                ));
            } catch (Throwable $exception) {
                Log::warning('LoL schedule sync failed', ['league' => $league->value, 'error' => $exception->getMessage()]);
            }
        }

        $hasCloseMatch = LolMatch::query()
            ->pending()
            ->where('start_time', '<=', now()->addMinutes(LolMatchWatcher::WATCH_WINDOW_MINUTES))
            ->where('start_time', '>=', now()->subHours(LolMatchWatcher::ABANDON_AFTER_HOURS))
            ->exists();

        if ($hasCloseMatch) {
            dispatch(new LolTickJob());
        }
    }

    /**
     * @return array<string, string>
     */
    private function homeLeagues(LolEsportsClient $client): array
    {
        try {
            /** @var array<string, string> */
            return Cache::remember('lol:team-home-leagues', now()->addDay(), $client->homeLeagues(...));
        } catch (Throwable $exception) {
            Log::warning('LoL team home leagues sync failed', ['error' => $exception->getMessage()]);

            return [];
        }
    }
}
