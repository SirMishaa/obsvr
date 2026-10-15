<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;
use Uri;

final class TwitchStreamerStreamStartedNotification extends Notification
{
    public function __construct(
        public string $streamerName,
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

        $url = Uri::of('https://twitch.tv')
            ->withPath($this->streamerName);

        return new WebPushMessage()
            ->title(__('twitch.stream_started', ['name' => $this->streamerName]))
            ->body(
                sprintf(
                    '%s (%s)',
                    __('twitch.stream_started', ['name' => $this->streamerName]),
                    now('Europe/Brussels')->format('H:i')
                )
            )
            ->data(['url' => (string) $url])
            ->action(__('twitch.cta.view_stream'), (string) $url);
    }
}
