<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class TwitchFollowedChannelsPaginatedResponse extends Data
{
    public function __construct(
        public int $total,
        #[DataCollectionOf(TwitchFollowedChannelsData::class)]
        public DataCollection $data,
        /** @var array{cursor?: string} */
        public array $pagination = [],
    ) {}

}
