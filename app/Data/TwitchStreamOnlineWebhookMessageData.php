<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TwitchStreamOnlineWebhookMessageData extends Data
{
    public function __construct(
        public string $id,

        #[MapInputName('broadcaster_user_id')]
        public string $broadcasterUserId,

        #[MapInputName('broadcaster_user_login')]
        public string $broadcasterUserLogin,

        #[MapInputName('broadcaster_user_name')]
        public string $broadcasterUserName,

        #[WithCast(DateTimeInterfaceCast::class, format: DATE_ATOM)]
        #[MapInputName('started_at')]
        public CarbonImmutable $startedAt,

        public string $type,
    ) {}
}
