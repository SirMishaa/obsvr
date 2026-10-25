<?php

declare(strict_types=1);

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use App\Services\LolEsportsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\LolEsportsResponses;

test('schedule parses match events of a league', function (): void {
    Http::fake([
        '*/getSchedule*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(),
            LolEsportsResponses::scheduleEvent(['type' => 'show', 'match' => ['id' => '1']]),
        ])),
    ]);

    $matches = resolve(LolEsportsClient::class)->schedule(LolLeague::Worlds);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->id)->toBe('115660588583171786')
        ->and($matches[0]->league)->toBe(LolLeague::Worlds)
        ->and($matches[0]->startTime->toIso8601ZuluString())->toBe('2026-10-15T18:00:00Z')
        ->and($matches[0]->state)->toBe('unstarted')
        ->and($matches[0]->blockName)->toBe('Play-Ins')
        ->and($matches[0]->bestOf)->toBe(5)
        ->and($matches[0]->teams)->toBe([
            ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'https://static.lolesports.com/teams/c9.png', 'outcome' => null, 'gameWins' => null],
            ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc.png', 'outcome' => null, 'gameWins' => null],
        ]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', '0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z')
        && $request['leagueId'] === '98767975604431411'
        && $request['hl'] === 'en-GB');
});

test('schedule keeps the result of completed matches', function (): void {
    Http::fake([
        '*/getSchedule*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(['state' => 'completed', 'match' => ['teams' => [
                ['result' => ['outcome' => 'loss', 'gameWins' => 2]],
                ['result' => ['outcome' => 'win', 'gameWins' => 3]],
            ]]]),
        ])),
    ]);

    $teams = resolve(LolEsportsClient::class)->schedule(LolLeague::Worlds)[0]->teams;

    expect(array_map(fn (array $team): array => [$team['outcome'], $team['gameWins']], $teams))->toBe([['loss', 2], ['win', 3]]);
});

test('home leagues are keyed by https team logo and skip archived teams', function (): void {
    Http::fake(['*/getTeams*' => Http::response(LolEsportsResponses::teams([
        ['KC', 'http://static.lolesports.com/teams/kc.png', 'LEC'],
        ['HLE', 'https://static.lolesports.com/teams/hle-old.png', 'LCK', 'archived'],
    ]))]);

    expect(resolve(LolEsportsClient::class)->homeLeagues())->toBe(['https://static.lolesports.com/teams/kc.png' => 'LEC']);
});

test('schedule follows the newer page until it covers the next day', function (): void {
    $this->travelTo('2026-10-15 12:00:00');

    Http::fake([
        '*/getSchedule*pageToken=next*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(['startTime' => '2026-10-20T18:00:00Z', 'match' => ['id' => '2']]),
        ])),
        '*/getSchedule*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(['startTime' => '2026-10-15T18:00:00Z', 'match' => ['id' => '1']]),
        ], newer: 'next')),
    ]);

    $matches = resolve(LolEsportsClient::class)->schedule(LolLeague::Worlds);

    expect(array_map(fn (LolMatchData $match): string => $match->id, $matches))->toBe(['1', '2']);
    Http::assertSentCount(2);
});

test('live returns ids of matches in progress', function (): void {
    Http::fake(['*/getLive*' => Http::response(LolEsportsResponses::live(['111', '222']))]);

    expect(resolve(LolEsportsClient::class)->live())->toBe(['111', '222']);
});

test('client throws on api errors', function (): void {
    Http::fake(['*' => Http::response(status: 500)]);

    resolve(LolEsportsClient::class)->live();
})->throws(RequestException::class);
