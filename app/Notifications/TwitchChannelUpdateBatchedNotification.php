<?php

namespace App\Notifications;

use App\Data\TwitchChannelUpdateMessageData;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Uri;

class TwitchChannelUpdateBatchedNotification extends Notification
{
    /**
     * @param  array<int, TwitchChannelUpdateMessageData>  $updates
     */
    public function __construct(
        public array $updates,
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $latest = end($this->updates);
        $count = count($this->updates);

        $streamerName = $latest->broadcasterUserLogin;
        $url = Uri::of('https://twitch.tv')
            ->withPath($streamerName);

        $lines = array_map(
            fn (TwitchChannelUpdateMessageData $update) => sprintf(
                '- "%s" (%s)',
                $update->title,
                $update->categoryName,
            ),
            $this->updates,
        );

        $body = implode("\n", array_unique($lines));

        if ($count > 1) {
            $body = __('twitch.batched_changes_count', ['count' => $count])."\n".$body;
        }

        return new WebPushMessage()
            ->title(__('twitch.stream_channel_updated', ['name' => $streamerName]))
            ->body($body)
            ->data(['url' => (string) $url])
            ->action(__('twitch.cta.view_stream'), (string) $url);
    }
}
