<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

final class LolMatchReminderNotification extends Notification
{
    public function __construct(
        public LolMatch $match,
        public LolLeagueSubscription $subscription,
        public int $minutesLeft,
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
        $url = $this->subscription->watchUrl();

        return new WebPushMessage()
            ->title('⏰ '.__('lol.match_reminder', ['match' => $this->match->title(), 'minutes' => $this->minutesLeft]))
            ->body($this->match->summary().' · '.$this->match->start_time->setTimezone('Europe/Brussels')->format('H:i'))
            ->icon($this->subscription->iconFor($this->match))
            ->data(['url' => $url])
            ->action($this->subscription->watchLabel(), $url);
    }
}
