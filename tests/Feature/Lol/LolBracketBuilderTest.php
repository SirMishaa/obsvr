<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Models\LolMatch;
use App\Services\LolBracketBuilder;

/**
 * @return list<array{name: string, code: string, image: string, outcome: string|null, gameWins: int|null}>
 */
function bracketTeams(string $first, string $second, ?string $winner = null): array
{
    return array_map(fn (string $code): array => [
        'name' => $code,
        'code' => $code,
        'image' => '',
        'outcome' => $winner === null || $code === 'TBD' ? null : ($code === $winner ? 'win' : 'loss'),
        'gameWins' => null,
    ], [$first, $second]);
}

/**
 * @param  list<array{name: string, code: string, image: string, outcome: string|null, gameWins: int|null}>  $teams
 */
function bracketMatch(string $startTime, string $blockName, array $teams, LolLeague $league = LolLeague::Worlds): LolMatch
{
    return LolMatch::factory()->startingAt(now()->parse($startTime))->create([
        'league' => $league,
        'block_name' => $blockName,
        'teams' => $teams,
        'state' => $teams[0]['outcome'] !== null ? 'completed' : 'unstarted',
    ]);
}

/**
 * @return list<array<string, mixed>>
 */
function bracketSections(LolLeague $league = LolLeague::Worlds): array
{
    $bracket = resolve(LolBracketBuilder::class)->build($league);
    expect($bracket)->not->toBeNull();

    return $bracket['sections'] ?? [];
}

test('a league without match has no bracket', function (): void {
    expect(resolve(LolBracketBuilder::class)->build(LolLeague::Worlds))->toBeNull();
});

test('swiss stage is sent as chronological matches with the wins needed to advance', function (): void {
    $this->travelTo('2026-10-25 12:00:00');
    $codes = ['GEN', 'IG', 'HLE', 'TES', 'MKOI', 'BLG', 'KC', 'AL', 'DK', 'G2', 'CFO', 'TLAW', 'T1', 'LOS', 'LYON', 'TSW'];
    foreach (array_chunk($codes, 2) as $index => [$first, $second]) {
        bracketMatch('2026-10-23 1'.$index.':00:00', 'Swiss', bracketTeams($first, $second, $first));
    }
    bracketMatch('2026-10-28 18:00:00', 'Swiss', bracketTeams('TBD', 'TBD'));

    $sections = bracketSections();

    expect($sections)->toHaveCount(1)
        ->and($sections[0])->toMatchArray(['kind' => 'swiss', 'teamCount' => 16, 'winsNeeded' => 3])
        ->and(data_get($sections, '0.matches'))->toHaveCount(9)
        ->and(data_get($sections, '0.matches.0.teams.0'))->toMatchArray(['code' => 'GEN', 'outcome' => 'win'])
        ->and(data_get($sections, '0.matches.8.teams.0.code'))->toBe('TBD');
});

test('knockout stages become one column each, after the swiss stage', function (): void {
    $this->travelTo('2026-11-05 12:00:00');
    bracketMatch('2026-10-23 10:00:00', 'Swiss', bracketTeams('GEN', 'IG', 'GEN'));
    bracketMatch('2026-11-03 10:00:00', 'Quarterfinals', bracketTeams('GEN', 'T1', 'GEN'));
    bracketMatch('2026-11-04 10:00:00', 'Quarterfinals', bracketTeams('KC', 'HLE'));
    bracketMatch('2026-11-08 10:00:00', 'Semifinals', bracketTeams('TBD', 'TBD'));

    $sections = bracketSections();

    expect(array_column($sections, 'kind'))->toBe(['swiss', 'bracket'])
        ->and(data_get($sections, '1.columns.*.title'))->toBe(['Quarterfinals', 'Semifinals'])
        ->and(data_get($sections, '1.columns.0.matches'))->toHaveCount(2)
        ->and(data_get($sections, '1.columns.1.matches'))->toHaveCount(1);
});

test('a lone knockout stage is split by match day and league weeks are left out', function (): void {
    $this->travelTo('2026-09-01 12:00:00');
    bracketMatch('2026-08-20 10:00:00', 'Week 9', bracketTeams('GEN', 'T1', 'GEN'), LolLeague::Lck);
    bracketMatch('2026-08-29 18:00:00', 'Playoffs', bracketTeams('GEN', 'T1', 'GEN'), LolLeague::Lck);
    bracketMatch('2026-08-29 22:00:00', 'Playoffs', bracketTeams('HLE', 'DK', 'DK'), LolLeague::Lck);
    bracketMatch('2026-08-30 18:00:00', 'Playoffs', bracketTeams('GEN', 'DK'), LolLeague::Lck);

    $sections = bracketSections(LolLeague::Lck);

    expect($sections)->toHaveCount(1)
        ->and(data_get($sections, '0.title'))->toBe('Playoffs')
        ->and(data_get($sections, '0.columns'))->toHaveCount(2)
        ->and(data_get($sections, '0.columns.0.matches'))->toHaveCount(2)
        ->and(data_get($sections, '0.columns.1.matches'))->toHaveCount(1);
});

test('the bracket shows the running tournament, not an older one', function (): void {
    $this->travelTo('2026-10-25 12:00:00');
    bracketMatch('2026-07-10 10:00:00', 'Finals', bracketTeams('GEN', 'BLG', 'GEN'));
    bracketMatch('2026-10-24 10:00:00', 'Swiss', bracketTeams('KC', 'T1', 'KC'));

    expect(array_column(bracketSections(), 'title'))->toBe(['Swiss']);
});

test('the latest tournament stays visible after it ended', function (): void {
    $this->travelTo('2026-08-01 12:00:00');
    bracketMatch('2026-07-10 10:00:00', 'Finals', bracketTeams('GEN', 'BLG', 'GEN'));

    expect(data_get(bracketSections(), '0.columns.0.matches.0.teams.0'))->toMatchArray(['code' => 'GEN', 'outcome' => 'win']);
});
