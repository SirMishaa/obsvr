<?php

namespace App\Enums;

enum TwitchSubscriptionType: string
{
    case STREAM_ONLINE = 'stream.online';
    case STREAM_OFFLINE = 'stream.offline';
    case CHANNEL_UPDATE = 'channel.update';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
