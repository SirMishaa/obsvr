<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TwitchEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTwitchEvent
 */
#[Fillable([
    'event_id',
    'event_type',
    'streamer_id',
    'streamer_name',
    'payload',
    'occurred_at',
    'received_at',
])]
final class TwitchEvent extends Model
{
    /** @use HasFactory<TwitchEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }
}
