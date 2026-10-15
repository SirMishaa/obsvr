<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use Database\Factories\LolLeagueSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property LolLeague $league
 * @property LolSubscriptionMode $mode
 * @property list<string>|null $team_codes
 * @property string|null $costream_channel
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'league',
    'mode',
    'team_codes',
    'costream_channel',
])]
final class LolLeagueSubscription extends Model
{
    /** @use HasFactory<LolLeagueSubscriptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(LolMatch $match): bool
    {
        if ($match->league !== $this->league) {
            return false;
        }

        if ($this->mode === LolSubscriptionMode::All) {
            return true;
        }

        $followed = array_diff($this->team_codes ?? [], ['TBD']);

        return array_intersect($followed, $match->teamCodes()) !== [];
    }

    public function watchUrl(): string
    {
        return $this->costream_channel !== null
            ? 'https://twitch.tv/'.$this->costream_channel
            : 'https://lolesports.com/live/'.$this->league->value;
    }

    public function watchLabel(): string
    {
        return $this->costream_channel !== null
            ? __('lol.cta.watch_on', ['channel' => $this->costream_channel])
            : __('lol.cta.watch');
    }

    public function iconFor(LolMatch $match): string
    {
        if ($this->mode === LolSubscriptionMode::Teams) {
            foreach ($match->teams as $team) {
                if (in_array($team['code'], $this->team_codes ?? [], true) && $team['image'] !== '') {
                    return $team['image'];
                }
            }
        }

        return $this->league->image();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'league' => LolLeague::class,
            'mode' => LolSubscriptionMode::class,
            'team_codes' => 'array',
        ];
    }
}
