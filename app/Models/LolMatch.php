<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use Carbon\CarbonImmutable;
use Database\Factories\LolMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property LolLeague $league
 * @property CarbonImmutable $start_time
 * @property string $state
 * @property string|null $block_name
 * @property int|null $best_of
 * @property list<array{name: string, code: string, image: string}> $teams
 * @property CarbonImmutable|null $watch_ended_at
 */
#[Fillable([
    'id',
    'league',
    'start_time',
    'state',
    'block_name',
    'best_of',
    'teams',
    'watch_ended_at',
])]
#[WithoutIncrementing]
final class LolMatch extends Model
{
    /** @use HasFactory<LolMatchFactory> */
    use HasFactory;

    protected $keyType = 'string';

    /**
     * @param  list<LolMatchData>  $matches
     */
    public static function upsertFromData(array $matches): void
    {
        if ($matches === []) {
            return;
        }

        self::query()->upsert(
            array_map(fn (LolMatchData $match): array => $match->toRow(), $matches),
            ['id'],
            ['start_time', 'state', 'block_name', 'best_of', 'teams', 'updated_at'],
        );
    }

    /**
     * @return list<string>
     */
    public function teamCodes(): array
    {
        return array_column($this->teams, 'code');
    }

    public function title(): string
    {
        return implode(' vs ', $this->teamCodes());
    }

    public function summary(): string
    {
        return implode(' · ', array_filter([
            $this->league->label(),
            $this->block_name,
            $this->best_of !== null ? 'BO'.$this->best_of : null,
        ]));
    }

    /**
     * @return Collection<int, LolLeagueSubscription>
     */
    public function subscriptions(): Collection
    {
        return LolLeagueSubscription::query()
            ->with('user')
            ->where('league', $this->league)
            ->get()
            ->filter(fn (LolLeagueSubscription $subscription): bool => $subscription->matches($this))
            ->values();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('watch_ended_at')->where('state', '!=', 'completed');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'league' => LolLeague::class,
            'start_time' => 'immutable_datetime',
            'teams' => 'array',
            'best_of' => 'integer',
            'watch_ended_at' => 'immutable_datetime',
        ];
    }
}
