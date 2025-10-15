<?php

namespace App\Notifications;

use App\Data\TwitchChannelUpdateMessageData;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Uri;

class TwitchChannelUpdatedNotification extends Notification
{
    public function __construct(
        public TwitchChannelUpdateMessageData $twitchChannelUpdateMessageData,
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {

        $streamerName = $this->twitchChannelUpdateMessageData->broadcasterUserLogin;
        $url = Uri::of('https://twitch.tv')
            ->withPath($streamerName);

        return new WebPushMessage()
            ->title(__('twitch.stream_channel_updated', ['name' => $streamerName]))
            ->body(
                sprintf(
                    '%s (%s)',
                    __('twitch.stream_title_updated_to', ['title' => $this->twitchChannelUpdateMessageData->title, 'category' => $this->twitchChannelUpdateMessageData->categoryName]),
                    now('Europe/Brussels')->format('H:i')
                )
            )
            ->data(['url' => (string) $url])
            ->action(__('twitch.cta.view_stream'), (string) $url);
    }
}
