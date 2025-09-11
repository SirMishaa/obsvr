<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

class TwitchEventSubSubscriptionItemData extends Data
{
    /**
     * @param  array<string, string>  $condition
     * @param  array<string, string|null>  $transport
     */
    public function __construct(
        public string $id,
        public string $status,
        public string $type,
        public string $version,
        public array $condition,

        #[MapInputName('created_at')]
        #[WithCast(DateTimeInterfaceCast::class, format: DATE_ATOM)]
        public string $createdAt,

        public array $transport,
        public int $cost,
    ) {}
}
