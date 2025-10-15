<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

final class TwitchStreamOnlineWebhookMessageData extends Data
{
    public function __construct(
        public string $id,

        #[MapInputName('broadcaster_user_id')]
        public string $broadcasterUserId,

        #[MapInputName('broadcaster_user_login')]
        public string $broadcasterUserLogin,

        #[MapInputName('broadcaster_user_name')]
        public string $broadcasterUserName,

        #[WithCast(DateTimeInterfaceCast::class, format: [
            'Y-m-d\TH:i:s.u\Z', // ex: 2025-10-03T00:20:19.407921Z (twitch-cli)
            'Y-m-d\TH:i:s\Z',   // ex: 2025-10-03T00:20:19Z
            'Y-m-d\TH:i:s.uP',  // ex: 2025-10-03T00:20:19.407921+00:00
            DATE_ATOM,          // ex: 2025-10-03T00:20:19+00:00
        ])]
        #[MapInputName('started_at')]
        public CarbonImmutable $startedAt,

        public string $type,
    ) {}
}
