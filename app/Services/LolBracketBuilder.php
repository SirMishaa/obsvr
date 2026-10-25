<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LolLeague;
use App\Models\LolMatch;
use Illuminate\Support\Collection;

/**
 * Lays out the current tournament of a league: a Swiss stage is sent as its chronological matches (the
 * page replays records into pools so results can be simulated), other stages become knockout columns.
 *
 * @phpstan-type BracketTeam array{code: string, name: string, image: string, homeLeague: string|null, outcome: string|null, gameWins: int|null}
 * @phpstan-type BracketMatch array{id: string, startTime: string, state: string, bestOf: int|null, teams: list<BracketTeam>}
 * @phpstan-type BracketColumn array{title: string|null, matches: list<BracketMatch>}
 * @phpstan-type SwissSection array{kind: 'swiss', title: string, winsNeeded: int, teamCount: int, matches: list<BracketMatch>}
 * @phpstan-type KnockoutSection array{kind: 'bracket', title: string, columns: list<BracketColumn>}
 * @phpstan-type BracketSection SwissSection|KnockoutSection
 */
final readonly class LolBracketBuilder
{
    private const int LOOKBACK_DAYS = 120;

    private const int TOURNAMENT_GAP_DAYS = 21;

    private const int RECENT_TOURNAMENT_DAYS = 7;

    private const int MATCH_DAY_GAP_HOURS = 12;

    private const string SWISS_BLOCK = 'Swiss';

    /**
     * @return array{league: string, leagueLabel: string, sections: list<BracketSection>}|null
     */
    public function build(LolLeague $league): ?array
    {
        $matches = $this->currentTournament($league);

        if ($matches->isEmpty()) {
            return null;
        }

        $sections = [];
        $knockoutStages = [];

        foreach ($matches->groupBy(fn (LolMatch $match): string => $match->block_name ?? '') as $blockName => $stageMatches) {
            $blockName = (string) $blockName;

            if (preg_match('/^Week \d+$/i', $blockName) === 1) {
                continue;
            }

            if ($blockName === self::SWISS_BLOCK) {
                if ($knockoutStages !== []) {
                    $sections[] = $this->knockoutSection($knockoutStages);
                    $knockoutStages = [];
                }

                $sections[] = $this->swissSection($stageMatches->values());

                continue;
            }

            $knockoutStages[$blockName] = $stageMatches->values();
        }

        if ($knockoutStages !== []) {
            $sections[] = $this->knockoutSection($knockoutStages);
        }

        return $sections === [] ? null : [
            'league' => $league->value,
            'leagueLabel' => $league->label(),
            'sections' => $sections,
        ];
    }

    /**
     * Matches of the tournament running now, or of the next/latest one. Tournaments are told apart by a long break between matches.
     *
     * @return Collection<int, LolMatch>
     */
    private function currentTournament(LolLeague $league): Collection
    {
        $matches = LolMatch::query()
            ->where('league', $league)
            ->where('start_time', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        /** @var list<list<LolMatch>> $tournaments */
        $tournaments = [];
        $previous = null;

        foreach ($matches as $match) {
            if ($previous === null || $previous->start_time->diffInDays($match->start_time) > self::TOURNAMENT_GAP_DAYS) {
                $tournaments[] = [];
            }

            $tournaments[array_key_last($tournaments)][] = $match;
            $previous = $match;
        }

        foreach ($tournaments as $tournament) {
            if ($tournament[count($tournament) - 1]->start_time->isAfter(now()->subDays(self::RECENT_TOURNAMENT_DAYS))) {
                return collect($tournament);
            }
        }

        return collect($tournaments === [] ? [] : end($tournaments));
    }

    /**
     * @param  Collection<int, LolMatch>  $matches
     * @return SwissSection
     */
    private function swissSection(Collection $matches): array
    {
        $teamCount = $matches->flatMap(fn (LolMatch $match): array => $match->teamCodes())
            ->reject(fn (string $code): bool => $code === 'TBD')
            ->unique()
            ->count();

        return [
            'kind' => 'swiss',
            'title' => self::SWISS_BLOCK,
            'winsNeeded' => max(1, (int) floor(log(max(2, $teamCount), 2)) - 1),
            'teamCount' => $teamCount,
            'matches' => array_values($matches->map($this->match(...))->all()),
        ];
    }

    /**
     * One column per stage; a lone stage is split by match day (untitled, the page shows the day) so its rounds still read as a bracket.
     *
     * @param  array<string, Collection<int, LolMatch>>  $stages
     * @return KnockoutSection
     */
    private function knockoutSection(array $stages): array
    {
        if (count($stages) === 1) {
            $title = (string) array_key_first($stages);
            /** @var list<list<BracketMatch>> $matchDays */
            $matchDays = [];
            $previous = null;

            foreach ($stages[$title] as $match) {
                if ($previous === null || $previous->start_time->diffInHours($match->start_time) > self::MATCH_DAY_GAP_HOURS) {
                    $matchDays[] = [];
                }

                $matchDays[count($matchDays) - 1][] = $this->match($match);
                $previous = $match;
            }

            return [
                'kind' => 'bracket',
                'title' => $title,
                'columns' => array_map(fn (array $matches): array => ['title' => null, 'matches' => $matches], $matchDays),
            ];
        }

        return [
            'kind' => 'bracket',
            'title' => 'Knockouts',
            'columns' => array_map(
                fn (string $title, Collection $stageMatches): array => [
                    'title' => $title,
                    'matches' => array_values($stageMatches->map($this->match(...))->all()),
                ],
                array_keys($stages),
                $stages,
            ),
        ];
    }

    /**
     * @return BracketMatch
     */
    private function match(LolMatch $match): array
    {
        return [
            'id' => $match->id,
            'startTime' => $match->start_time->toIso8601ZuluString(),
            'state' => $match->state,
            'bestOf' => $match->best_of,
            'teams' => $this->teams($match),
        ];
    }

    /**
     * @return list<BracketTeam>
     */
    private function teams(LolMatch $match): array
    {
        return array_map(fn (array $team): array => [
            'code' => $team['code'],
            'name' => $team['name'],
            'image' => $team['image'],
            'homeLeague' => $team['homeLeague'] ?? null,
            'outcome' => $team['outcome'] ?? null,
            'gameWins' => $team['gameWins'] ?? null,
        ], $match->teams);
    }
}
