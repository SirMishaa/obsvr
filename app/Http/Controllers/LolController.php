<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use App\Http\Requests\UpdateLolLeagueSubscriptionRequest;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;
use App\Services\LolMatchWatcher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LolController extends Controller
{
    private const int TEAM_CHIPS_LOOKBACK_DAYS = 60;

    public function toggleLeague(LolLeague $league): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $subscription = $user->lolLeagueSubscriptions()->firstOrCreate(['league' => $league]);

        if ($subscription->wasRecentlyCreated) {
            dispatch(new SyncLolScheduleJob());
        } else {
            $subscription->delete();
        }

        return back(status: 303);
    }

    public function index(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $subscriptions = $user->lolLeagueSubscriptions()->get()
            ->keyBy(fn (LolLeagueSubscription $subscription): string => $subscription->league->value);

        return Inertia::render('Lol', [
            'leagues' => array_map(function (LolLeague $league) use ($subscriptions): array {
                /** @var LolLeagueSubscription|null $subscription */
                $subscription = $subscriptions->get($league->value);

                return [
                    'slug' => $league->value,
                    'label' => $league->label(),
                    'image' => $league->image(),
                    'subscribed' => $subscription !== null,
                    'mode' => ($subscription->mode ?? LolSubscriptionMode::All)->value,
                    'teamCodes' => $subscription->team_codes ?? [],
                    'costreamChannel' => $subscription?->costream_channel,
                    'teams' => $subscription !== null ? $this->teamsOf($league) : [],
                ];
            }, LolLeague::cases()),
            'upcomingMatches' => $this->upcomingMatches($subscriptions),
        ]);
    }

    public function updateLeague(UpdateLolLeagueSubscriptionRequest $request, LolLeague $league): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $subscription = $user->lolLeagueSubscriptions()->where('league', $league)->firstOrFail();
        $mode = $request->enum('mode', LolSubscriptionMode::class);

        $subscription->update([
            'mode' => $mode,
            'team_codes' => $mode === LolSubscriptionMode::Teams ? array_values($request->array('team_codes')) : null,
            'costream_channel' => $request->string('costream_channel')->trim()->value() ?: null,
        ]);

        return back(status: 303);
    }

    /**
     * Teams of the league's recent matches; the most recent name/logo wins when a team rebrands under the same code.
     *
     * @return list<array{code: string, name: string, image: string}>
     */
    private function teamsOf(LolLeague $league): array
    {
        return array_values(LolMatch::query()
            ->where('league', $league)
            ->where('start_time', '>=', now()->subDays(self::TEAM_CHIPS_LOOKBACK_DAYS))
            ->orderByDesc('start_time')
            ->get()
            ->flatMap(fn (LolMatch $match): array => $match->teams)
            ->reject(fn (array $team): bool => $team['code'] === 'TBD')
            ->unique('code')
            ->sortBy('code')
            ->map(fn (array $team): array => ['code' => $team['code'], 'name' => $team['name'], 'image' => $team['image']])
            ->all());
    }

    /**
     * @param  Collection<string, LolLeagueSubscription>  $subscriptions
     * @return list<array<string, mixed>>
     */
    private function upcomingMatches(Collection $subscriptions): array
    {
        if ($subscriptions->isEmpty()) {
            return [];
        }

        return array_values(LolMatch::query()
            ->whereIn('league', $subscriptions->keys()->all())
            ->where('state', '!=', 'completed')
            ->whereBetween('start_time', [now()->subHours(LolMatchWatcher::ABANDON_AFTER_HOURS), now()->addDays(7)])
            ->orderBy('start_time')
            ->limit(40)
            ->get()
            ->map(fn (LolMatch $match): array => [
                'id' => $match->id,
                'league' => $match->league->value,
                'leagueLabel' => $match->league->label(),
                'startTime' => $match->start_time->toIso8601ZuluString(),
                'state' => $match->state,
                'blockName' => $match->block_name,
                'bestOf' => $match->best_of,
                'teams' => $match->teams,
                'notified' => $subscriptions->get($match->league->value)?->matches($match) ?? false,
            ])
            ->all());
    }
}
