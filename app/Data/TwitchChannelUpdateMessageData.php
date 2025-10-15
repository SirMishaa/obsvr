<?php

namespace App\Data;

use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Data;

final class TwitchChannelUpdateMessageData extends Data
{
    /**
     * @param  list<string>  $contentClassificationLabels
     */
    public function __construct(
        #[MapInputName('broadcaster_user_id')]
        public string $broadcasterUserId,

        #[MapInputName('broadcaster_user_login')]
        public string $broadcasterUserLogin,

        #[MapInputName('broadcaster_user_name')]
        public string $broadcasterUserName,

        public string $title,
        public string $language,

        #[MapInputName('category_id')]
        public string $categoryId,

        #[MapInputName('category_name')]
        public string $categoryName,

        #[MapInputName('content_classification_labels')]
        public array $contentClassificationLabels,
    ) {}
}
