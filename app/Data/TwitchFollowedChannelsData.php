<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

final class TwitchFollowedChannelsData extends Data
{
    public function __construct(
        #[MapInputName('broadcaster_id')]
        public string $broadcasterId,

        #[MapInputName('broadcaster_name')]
        public string $broadcasterName,

        #[MapInputName('broadcaster_login')]
        public string $broadcasterLogin,

        #[MapInputName('followed_at')]
        #[WithCast(DateTimeInterfaceCast::class)]
        public CarbonImmutable $followedAt,

    ) {}
}
