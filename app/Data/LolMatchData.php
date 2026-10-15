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
     * @param  array<string, mixed>  $event
     */
    public static function fromScheduleEvent(array $event, LolLeague $league): self
    {
        /** @var array{id: string, teams?: list<array<string, mixed>>, strategy?: array{count?: int}} $match */
        $match = $event['match'];

        return new self(
            id: (string) $match['id'],
            league: $league,
            startTime: CarbonImmutable::parse((string) $event['startTime'])->utc(),
            state: (string) $event['state'],
            blockName: isset($event['blockName']) ? (string) $event['blockName'] : null,
            bestOf: isset($match['strategy']['count']) ? (int) $match['strategy']['count'] : null,
            teams: array_map(fn (array $team): array => [
                'name' => (string) ($team['name'] ?? 'TBD'),
                'code' => (string) ($team['code'] ?? 'TBD'),
                'image' => (string) preg_replace('#^http://#', 'https://', (string) ($team['image'] ?? '')),
            ], array_values($match['teams'] ?? [])),
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
}
