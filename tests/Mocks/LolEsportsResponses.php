<?php

declare(strict_types=1);

namespace Tests\Mocks;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;

final class LolEsportsResponses
{
    public const string MATCH_ID = '115660588583171786';

    /**
     * Current fake API state. Http::fake() stubs stack (first registered wins), so the stubs read this
     * state lazily and fake() can be called again within a test to change the API responses.
     *
     * @var array{state: string, startTime: string, liveIds: list<string>}
     */
    private static array $fakeState = ['state' => 'unstarted', 'startTime' => '2026-10-15T18:00:00Z', 'liveIds' => []];

    /**
     * Fake getSchedule (one match with given state/start) and getLive (given ids in progress).
     *
     * @param  list<string>  $liveIds
     */
    public static function fake(string $state = 'unstarted', string $startTime = '2026-10-15T18:00:00Z', array $liveIds = []): void
    {
        self::$fakeState = ['state' => $state, 'startTime' => $startTime, 'liveIds' => $liveIds];

        Http::fake([
            '*/getSchedule*' => fn (): PromiseInterface => Http::response(self::schedule([
                self::scheduleEvent(['state' => self::$fakeState['state'], 'startTime' => self::$fakeState['startTime']]),
            ])),
            '*/getLive*' => fn (): PromiseInterface => Http::response(self::live(self::$fakeState['liveIds'])),
            '*/getTeams*' => Http::response(self::teams([])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<mixed>
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
     * @param  list<array<mixed>>  $events
     * @return array<string, mixed>
     */
    public static function schedule(array $events, ?string $newer = null): array
    {
        return ['data' => ['schedule' => ['pages' => ['older' => null, 'newer' => $newer], 'events' => $events]]];
    }

    /**
     * getTeams payload; each team is [code, image, home league name, status].
     *
     * @param  list<array{0: string, 1: string, 2: string, 3?: string}>  $teams
     * @return array<string, mixed>
     */
    public static function teams(array $teams): array
    {
        return ['data' => ['teams' => array_map(fn (array $team): array => [
            'code' => $team[0],
            'image' => $team[1],
            'status' => $team[3] ?? 'active',
            'homeLeague' => ['name' => $team[2], 'region' => 'EUROPE'],
        ], $teams)]];
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
