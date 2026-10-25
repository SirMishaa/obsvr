<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\LolLeague;
use Carbon\CarbonImmutable;

final readonly class LolMatchData
{
    /**
     * @param  list<array{name: string, code: string, image: string, outcome: string|null, gameWins: int|null, homeLeague?: string|null}>  $teams
     */
    public function __construct(
        public string $id,
        public LolLeague $league,
        public CarbonImmutable $startTime,
        public string $state,
        public ?string $blockName,
        public ?int $bestOf,
        public array $teams,
    ) {}

    /**
     * @param  array<mixed>  $event
     */
    public static function fromScheduleEvent(array $event, LolLeague $league): self
    {
        $match = is_array($event['match'] ?? null) ? $event['match'] : [];
        $strategy = is_array($match['strategy'] ?? null) ? $match['strategy'] : [];
        $teams = is_array($match['teams'] ?? null) ? $match['teams'] : [];
        $blockName = self::string($event['blockName'] ?? null);
        $bestOf = $strategy['count'] ?? null;

        return new self(
            id: (string) self::matchId($event),
            league: $league,
            startTime: CarbonImmutable::parse(self::string($event['startTime'] ?? null))->utc(),
            state: self::string($event['state'] ?? null),
            blockName: $blockName !== '' ? $blockName : null,
            bestOf: is_int($bestOf) ? $bestOf : null,
            teams: array_values(array_map(self::team(...), $teams)),
        );
    }

    /**
     * The lolesports match id of a schedule/live event, or null when the event is not a match.
     *
     * @param  array<mixed>  $event
     */
    public static function matchId(array $event): ?string
    {
        $match = $event['match'] ?? null;
        $id = is_array($match) ? $match['id'] ?? null : null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Tag each team with the league it comes from (LCK, LEC...), looked up by team logo.
     *
     * @param  array<string, string>  $homeLeagues
     */
    public function withHomeLeagues(array $homeLeagues): self
    {
        return new self(
            id: $this->id,
            league: $this->league,
            startTime: $this->startTime,
            state: $this->state,
            blockName: $this->blockName,
            bestOf: $this->bestOf,
            teams: array_map(fn (array $team): array => [
                ...$team,
                'homeLeague' => $team['code'] !== 'TBD' ? $homeLeagues[$team['image']] ?? null : null,
            ], $this->teams),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'league' => $this->league->value,
            'start_time' => $this->startTime->toDateTimeString(),
            'state' => $this->state,
            'block_name' => $this->blockName,
            'best_of' => $this->bestOf,
            'teams' => json_encode($this->teams, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * @return array{name: string, code: string, image: string, outcome: string|null, gameWins: int|null}
     */
    private static function team(mixed $team): array
    {
        $team = is_array($team) ? $team : [];
        $result = is_array($team['result'] ?? null) ? $team['result'] : [];
        $outcome = $result['outcome'] ?? null;
        $gameWins = $result['gameWins'] ?? null;

        return [
            'name' => self::string($team['name'] ?? null, 'TBD'),
            'code' => self::string($team['code'] ?? null, 'TBD'),
            'image' => (string) preg_replace('#^http://#', 'https://', self::string($team['image'] ?? null)),
            'outcome' => in_array($outcome, ['win', 'loss'], true) ? $outcome : null,
            'gameWins' => is_int($gameWins) ? $gameWins : null,
        ];
    }

    private static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) || is_int($value) ? (string) $value : $default;
    }
}
