<?php

namespace App\Models;

use App\Enums\TwitchSubscriptionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSubscriptions
 */
class Subscriptions extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'status',
        'batch_delay',
        'favourite_streamer_id',
    ];

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

    public function favouriteStreamer(): BelongsTo
    {
        return $this->belongsTo(FavouriteStreamer::class);
    }
}
