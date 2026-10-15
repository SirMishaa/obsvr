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

final readonly class LolMatchWatcher
{
    public const int WATCH_WINDOW_MINUTES = 90;

    public const int ABANDON_AFTER_HOURS = 4;

    private const int MIN_DELAY_SECONDS = 60;

    private const int MAX_DELAY_SECONDS = 900;

    private const int LIVE_POLL_LEAD_SECONDS = 120;

    public function __construct(private LolEsportsClient $client) {}

    /**
     * Run one watch iteration and return the seconds until the next one, or null to stop.
     */
    public function tick(): ?int
    {
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
            return null;
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

            if ($secondsLeft >= 60 && LolMatchNotification::claim($match, $subscription->user, LolNotificationType::Reminder)) {
                $subscription->user->notify(new LolMatchReminderNotification(
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
            if (LolMatchNotification::claim($match, $subscription->user, LolNotificationType::Started)) {
                $subscription->user->notify(new LolMatchStartedNotification($match, $subscription));
            }
        });
    }

    private function endWatch(LolMatch $match): void
    {
        $match->update(['watch_ended_at' => now()]);
    }
}
