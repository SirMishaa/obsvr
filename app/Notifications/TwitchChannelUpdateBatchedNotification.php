<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Data\TwitchChannelUpdateMessageData;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Uri;

final class TwitchChannelUpdateBatchedNotification extends Notification
{
    /**
     * @param  array<int, TwitchChannelUpdateMessageData>  $updates
     */
    public function __construct(
        public array $updates,
    ) {}

    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $latest = end($this->updates);
        $count = count($this->updates);

        $streamerName = $latest->broadcasterUserLogin;
        $url = Uri::of('https://twitch.tv')
            ->withPath($streamerName);

        $lines = array_map(
            fn (TwitchChannelUpdateMessageData $update): string => sprintf(
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
