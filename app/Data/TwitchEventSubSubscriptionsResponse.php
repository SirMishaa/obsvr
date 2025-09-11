<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class TwitchEventSubSubscriptionsResponse extends Data
{
    public function __construct(
        public int $total,

        #[MapInputName('total_cost')]
        public int $totalCost,

        #[MapInputName('max_total_cost')]
        public int $maxTotalCost,

        /** Todo: replace me by using type directly instead of attributes, see https://spatie.be/docs/laravel-data/v4/as-a-data-transfer-object/nesting */
        #[DataCollectionOf(TwitchEventSubSubscriptionItemData::class)]
        public DataCollection $data,

        /** @var array{cursor?: string} */
        public array $pagination = [],
    ) {}
}
