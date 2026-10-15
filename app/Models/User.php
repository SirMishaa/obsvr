<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

/**
 * @mixin IdeHelperUser
 */
#[Fillable([
    'name',
    'email',
    'password',
    'auth_provider',
    'auth_provider_id',
    'avatar_url',
    'lol_reminder_minutes',
])]
#[Hidden([
    'password',
    'remember_token',
    'auth_provider_access_token',
    'auth_provider_refresh_token',
])]
final class User extends Authenticatable
{
    use HasFactory;
    use HasPushSubscriptions;
    use Notifiable;

    /**
     * @return HasMany<FavouriteStreamer>
     */
    public function favouriteStreamers(): HasMany
    {
        return $this->hasMany(FavouriteStreamer::class);
    }

    /**
     * @return HasMany<LolLeagueSubscription, $this>
     */
    public function lolLeagueSubscriptions(): HasMany
    {
        return $this->hasMany(LolLeagueSubscription::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'auth_provider' => AuthProvider::class,
            'auth_provider_access_token' => 'encrypted',
            'auth_provider_refresh_token' => 'encrypted',
            'auth_provider_expires_at' => 'immutable_datetime',
            'lol_reminder_minutes' => 'integer',
        ];
    }
}
