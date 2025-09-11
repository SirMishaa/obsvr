<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Uri;

class TwitchStreamerStreamStartedNotification extends Notification
{
    public function __construct(
        public string $streamerName,
    ) {}

    public function via($notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {

        $url = Uri::of('https://twitch.tv')
            ->withPath($this->streamerName);

        return new WebPushMessage()
            ->title('Stream started!')
            ->body(sprintf('Streamer %s started a stream', $this->streamerName))
            ->data(['url' => (string) $url])
            ->action('View Stream', (string) $url);
    }
}
