<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class TwitchStreamPaginatedResponse extends Data
{
    public function __construct(
        #[DataCollectionOf(TwitchStreamData::class)]
        public DataCollection $data,
        /** @var array{cursor?: string} */
        public array $pagination = [],
    ) {}

}
