<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LolNotificationType;
use Database\Factories\LolMatchNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[WithoutTimestamps]
final class LolMatchNotification extends Model
{
    /** @use HasFactory<LolMatchNotificationFactory> */
    use HasFactory;

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
