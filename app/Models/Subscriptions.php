<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TwitchSubscriptionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSubscriptions
 */
#[Fillable([
    'type',
    'status',
    'batch_delay',
    'favourite_streamer_id',
])]
final class Subscriptions extends Model
{
    use HasFactory;

    public function favouriteStreamer(): BelongsTo
    {
        return $this->belongsTo(FavouriteStreamer::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TwitchSubscriptionType::class,
            'batch_delay' => 'integer',
        ];
    }
}
