<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LolNotificationType;
use Illuminate\Database\Eloquent\Model;

final class LolMatchNotification extends Model
{
    public $timestamps = false;

    /**
     * Record the notification; returns false when it was already sent (at-least-once safe).
     */
    public static function claim(LolMatch $match, User $user, LolNotificationType $type): bool
    {
        return self::query()->insertOrIgnore([
            'lol_match_id' => $match->id,
            'user_id' => $user->id,
            'type' => $type->value,
            'sent_at' => now(),
        ]) === 1;
    }
}
