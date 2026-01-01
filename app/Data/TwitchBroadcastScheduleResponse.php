<?php

namespace App\Data;

use Spatie\LaravelData\Data;

final class TwitchBroadcastScheduleResponse extends Data
{
    public function __construct(
        /**
         * @var array{
         *     broadcaster_id: string,
         *     broadcaster_name: string,
         *     broadcaster_login: string,
         *     segments: array<int, array{
         *         id: string,
         *         start_time: string,
         *         end_time: string,
         *         title: string,
         *         canceled_until: ?string,
         *         category: array{id: string, name: string}|null,
         *         is_recurring: bool
         *     }>,
         *     vacation: ?array{start_time: string, end_time: string}
         * }
         */
        public array $data,

        /**
         * @var array{cursor?: string}
         */
        public array $pagination = [],
    ) {}
}
