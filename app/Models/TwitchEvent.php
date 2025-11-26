<?php

namespace App\Models;

use Database\Factories\TwitchEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperTwitchEvent
 */
class TwitchEvent extends Model
{
    /** @use HasFactory<TwitchEventFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_type',
        'streamer_id',
        'streamer_name',
        'payload',
        'occurred_at',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'received_at' => 'immutable_datetime',
        ];
    }
}
