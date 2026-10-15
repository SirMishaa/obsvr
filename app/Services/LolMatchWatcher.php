<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LolLeague;
use App\Enums\LolNotificationType;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\LolMatchNotification;
use App\Notifications\LolMatchReminderNotification;
use App\Notifications\LolMatchStartedNotification;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Throwable;

final class LolMatchWatcher
{
    public const int WATCH_WINDOW_MINUTES = 90;

    public const int ABANDON_AFTER_HOURS = 4;

    private const int MIN_DELAY_SECONDS = 60;

    private const int MAX_DELAY_SECONDS = 900;

    private const int LIVE_POLL_LEAD_SECONDS = 120;

    private bool $hadDeliveryFailure = false;

    public function __construct(private readonly LolEsportsClient $client) {}

    /**
     * Run one watch iteration and return the seconds until the next one, or null to stop.
     */
    public function tick(): ?int
    {
        $this->abandonStaleMatches();
        $this->hadDeliveryFailure = false;

        $matches = $this->watchedMatches();
        if ($matches->isEmpty()) {
            return null;
        }

        foreach ($matches->pluck('league')->unique() as $league) {
            /** @var LolLeague $league */
            LolMatch::upsertFromData($this->client->schedule($league));
        }

        $matches = $this->watchedMatches();
        $liveIds = $matches->isEmpty() ? [] : $this->client->live();
        $nextDueAt = null;

        foreach ($matches as $match) {
            if ($match->state === 'completed') {
                $this->endWatch($match);

                continue;
            }

            if ($match->state === 'inProgress' || in_array($match->id, $liveIds, true)) {
                $this->notifyStarted($match);
                $this->endWatch($match);

                continue;
            }

            if ($match->start_time->addHours(self::ABANDON_AFTER_HOURS)->isPast()) {
                $this->endWatch($match);

                continue;
            }

            $dueAt = $this->sendDueReminders($match);
            $nextDueAt = $nextDueAt?->min($dueAt) ?? $dueAt;
        }

        if ($nextDueAt === null) {
            return $this->hadDeliveryFailure ? self::MIN_DELAY_SECONDS : null;
        }

        if ($this->hadDeliveryFailure) {
            return self::MIN_DELAY_SECONDS;
        }

        $seconds = (int) now()->diffInSeconds($nextDueAt, absolute: false);

        return max(self::MIN_DELAY_SECONDS, min(self::MAX_DELAY_SECONDS, $seconds));
    }

    /**
     * @return Collection<int, LolMatch>
     */
    private function watchedMatches(): Collection
    {
        return LolMatch::query()
            ->pending()
            ->where('start_time', '<=', now()->addMinutes(self::WATCH_WINDOW_MINUTES))
            ->get();
    }

    /**
     * Send reminders that are due and return when this match next needs attention.
     */
    private function sendDueReminders(LolMatch $match): CarbonImmutable
    {
        $liveCheckAt = $match->start_time->subSeconds(self::LIVE_POLL_LEAD_SECONDS);
        $nextDueAt = $liveCheckAt;
        $secondsLeft = (int) now()->diffInSeconds($match->start_time, absolute: false);

        foreach ($match->subscriptions() as $subscription) {
            $offset = $subscription->user->lol_reminder_minutes;
            if ($offset === null) {
                continue;
            }

            $reminderAt = $match->start_time->subMinutes($offset);

            if ($reminderAt->isFuture()) {
                $nextDueAt = $nextDueAt->min($reminderAt);

                continue;
            }

            if ($secondsLeft >= 60) {
                $this->sendOnce($match, $subscription, LolNotificationType::Reminder, new LolMatchReminderNotification(
                    $match,
                    $subscription,
                    (int) ceil($secondsLeft / 60),
                ));
            }
        }

        return $nextDueAt;
    }

    private function notifyStarted(LolMatch $match): void
    {
        $match->subscriptions()->each(function (LolLeagueSubscription $subscription) use ($match): void {
            $this->sendOnce($match, $subscription, LolNotificationType::Started, new LolMatchStartedNotification($match, $subscription));
        });
    }

    /**
     * Claim then push; a failed push releases its claim so the next tick retries it, without blocking other users.
     */
    private function sendOnce(LolMatch $match, LolLeagueSubscription $subscription, LolNotificationType $type, Notification $notification): void
    {
        if (! LolMatchNotification::claim($match, $subscription->user, $type)) {
            return;
        }

        try {
            $subscription->user->notify($notification);
        } catch (Throwable $throwable) {
            report($throwable);
            LolMatchNotification::query()
                ->where('lol_match_id', $match->id)
                ->where('user_id', $subscription->user_id)
                ->where('type', $type->value)
                ->delete();
            $this->hadDeliveryFailure = true;
        }
    }

    /**
     * End matches past the abandon window up-front, so an API outage cannot keep the chain alive forever.
     */
    private function abandonStaleMatches(): void
    {
        LolMatch::query()
            ->pending()
            ->where('start_time', '<', now()->subHours(self::ABANDON_AFTER_HOURS))
            ->update(['watch_ended_at' => now()]);
    }

    private function endWatch(LolMatch $match): void
    {
        $match->update(['watch_ended_at' => now()]);
    }
}
