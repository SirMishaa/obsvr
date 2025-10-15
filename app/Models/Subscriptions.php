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
        'favourite_streamer_id',
    ];

    protected $casts = [
        'type' => TwitchSubscriptionType::class,
    ];

    public function favouriteStreamer(): BelongsTo
    {
        return $this->belongsTo(FavouriteStreamer::class);
    }
}
