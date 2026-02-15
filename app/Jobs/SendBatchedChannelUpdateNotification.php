<?php

namespace App\Jobs;

use App\Data\TwitchChannelUpdateMessageData;
use App\Models\FavouriteStreamer;
use App\Notifications\TwitchChannelUpdateBatchedNotification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendBatchedChannelUpdateNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $favouriteStreamerId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->favouriteStreamerId;
    }

    public function handle(): void
    {
        $cacheKey = "channel_update_batch:{$this->favouriteStreamerId}";

        /** @var array<int, array<string, mixed>>|null $updates */
        $updates = Cache::pull($cacheKey);

        if (empty($updates)) {
            Log::debug('No batched channel updates found for favourite streamer', [
                'favourite_streamer_id' => $this->favouriteStreamerId,
            ]);

            return;
        }

        $messages = array_map(
            fn (array $data) => TwitchChannelUpdateMessageData::from($data),
            $updates,
        );

        $favouriteStreamer = FavouriteStreamer::with('user')->find($this->favouriteStreamerId);

        if (! $favouriteStreamer) {
            return;
        }

        $favouriteStreamer->user->notifyNow(new TwitchChannelUpdateBatchedNotification($messages));

        Log::info(sprintf(
            'Sent batched channel update notification for streamer %s with %d updates',
            $favouriteStreamer->streamer_name,
            count($messages),
        ));
    }
}
