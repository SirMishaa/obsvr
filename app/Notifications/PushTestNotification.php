<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * Sent on demand to check the whole push chain (server → push service → service worker).
 */
final class PushTestNotification extends Notification
{
    /**
     * @return array<int, class-string>
     */
    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return new WebPushMessage()
            ->title(__('twitch.push_test.title'))
            ->body(sprintf('%s (%s)', __('twitch.push_test.body'), now('Europe/Brussels')->format('H:i:s')))
            ->data(['type' => 'test', 'url' => route('twitch')]);
    }
}
