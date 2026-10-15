<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class LolEsportsClient
{
    private const int MAX_SCHEDULE_PAGES = 3;

    /**
     * @return list<LolMatchData>
     */
    public function schedule(LolLeague $league): array
    {
        $matches = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_SCHEDULE_PAGES; $page++) {
            /** @var array{pages?: array{newer?: string|null}, events?: list<array<string, mixed>>} $schedule */
            $schedule = $this->request()
                ->get('/getSchedule', array_filter([
                    'hl' => 'en-GB',
                    'leagueId' => $league->leagueId(),
                    'pageToken' => $pageToken,
                ]))
                ->json('data.schedule', []);

            foreach ($schedule['events'] ?? [] as $event) {
                if (($event['type'] ?? null) === 'match' && isset($event['match']['id'])) {
                    $matches[] = LolMatchData::fromScheduleEvent($event, $league);
                }
            }

            $pageToken = $schedule['pages']['newer'] ?? null;
            $lastMatch = end($matches);

            if ($pageToken === null || ($lastMatch !== false && $lastMatch->startTime->isAfter(now()->addDay()))) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    public function live(): array
    {
        /** @var list<array<string, mixed>> $events */
        $events = $this->request()->get('/getLive', ['hl' => 'en-GB'])->json('data.schedule.events', []);

        $ids = [];
        foreach ($events as $event) {
            if (($event['state'] ?? null) === 'inProgress' && isset($event['match']['id'])) {
                $ids[] = (string) $event['match']['id'];
            }
        }

        return $ids;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.lolesports.base_url'))
            ->withHeaders(['x-api-key' => (string) config('services.lolesports.api_key')])
            ->acceptJson()
            ->timeout(10)
            ->throw();
    }
}
