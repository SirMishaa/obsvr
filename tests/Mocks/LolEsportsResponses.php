<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Illuminate\Support\Facades\Http;

final class LolEsportsResponses
{
    public const string MATCH_ID = '115660588583171786';

    /**
     * Fake getSchedule (one match with given state/start) and getLive (given ids in progress).
     *
     * @param  list<string>  $liveIds
     */
    public static function fake(string $state = 'unstarted', string $startTime = '2026-10-15T18:00:00Z', array $liveIds = []): void
    {
        Http::fake([
            '*/getSchedule*' => Http::response(self::schedule([
                self::scheduleEvent(['state' => $state, 'startTime' => $startTime]),
            ])),
            '*/getLive*' => Http::response(self::live($liveIds)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function scheduleEvent(array $overrides = []): array
    {
        return array_replace_recursive([
            'startTime' => '2026-10-15T18:00:00Z',
            'state' => 'unstarted',
            'type' => 'match',
            'blockName' => 'Play-Ins',
            'league' => ['name' => 'Worlds', 'slug' => 'worlds'],
            'match' => [
                'id' => '115660588583171786',
                'flags' => [],
                'teams' => [
                    ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'http://static.lolesports.com/teams/c9.png', 'result' => null, 'record' => null],
                    ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'http://static.lolesports.com/teams/kc.png', 'result' => null, 'record' => null],
                ],
                'strategy' => ['type' => 'bestOf', 'count' => 5],
            ],
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public static function schedule(array $events, ?string $newer = null): array
    {
        return ['data' => ['schedule' => ['pages' => ['older' => null, 'newer' => $newer], 'events' => $events]]];
    }

    /**
     * @param  list<string>  $matchIds
     * @return array<string, mixed>
     */
    public static function live(array $matchIds): array
    {
        return ['data' => ['schedule' => ['events' => array_map(
            fn (string $id): array => self::scheduleEvent(['state' => 'inProgress', 'match' => ['id' => $id]]),
            $matchIds,
        )]]];
    }
}
