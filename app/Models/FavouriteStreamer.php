<?php

namespace App\Models;

use App\Enums\TwitchSubscriptionStatus;
use App\Observers\UserFavouriteStreamerObserver;
use Database\Factories\FavouriteStreamerFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperFavouriteStreamer
 */
#[ObservedBy(UserFavouriteStreamerObserver::class)]
class FavouriteStreamer extends Model
{
    /** @use HasFactory<FavouriteStreamerFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'streamer_id',
        'streamer_name',
        'subscription_status',
    ];

    protected $casts = [
        'subscription_status' => TwitchSubscriptionStatus::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Subscriptions, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscriptions::class);
    }
}
