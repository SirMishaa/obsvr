<?php

namespace App\Data;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

final class TwitchStreamData extends Data
{
    public function __construct(
        public string $id,

        #[MapInputName('user_id')]
        public string $userId,

        #[MapInputName('user_login')]
        public string $userLogin,

        #[MapInputName('user_name')]
        public string $userName,

        #[MapInputName('game_id')]
        public string $gameId,

        #[MapInputName('game_name')]
        public string $gameName,

        public string $type,
        public string $title,

        #[MapInputName('viewer_count')]
        public int $viewerCount,

        #[MapInputName('started_at')]
        #[WithCast(DateTimeInterfaceCast::class)]
        public CarbonImmutable $startedAt,

        public string $language,

        #[MapInputName('thumbnail_url')]
        public string $thumbnailUrl,

        /** @var array<int, string> */
        #[MapInputName('tag_ids')]
        public array $tagIds,

        /** @var array<int, string> */
        public array $tags,

        #[MapInputName('is_mature')]
        public bool $isMature,
    ) {}
}
