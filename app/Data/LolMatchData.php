<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\LolLeague;
use Carbon\CarbonImmutable;

final readonly class LolMatchData
{
    /**
     * @param  list<array{name: string, code: string, image: string}>  $teams
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
            teams: array_values(array_map(fn (mixed $team): array => [
                'name' => self::string(is_array($team) ? $team['name'] ?? null : null, 'TBD'),
                'code' => self::string(is_array($team) ? $team['code'] ?? null : null, 'TBD'),
                'image' => (string) preg_replace('#^http://#', 'https://', self::string(is_array($team) ? $team['image'] ?? null : null)),
            ], $teams)),
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

    private static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) || is_int($value) ? (string) $value : $default;
    }
}
