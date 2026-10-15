<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LolLeague;
use App\Jobs\SyncLolScheduleJob;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final class LolController extends Controller
{
    public function index(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        $subscribedLeagues = $user->lolLeagueSubscriptions()->pluck('league')
            ->map(fn (LolLeague $league): string => $league->value)
            ->all();

        return Inertia::render('Lol', [
            'leagues' => array_map(fn (LolLeague $league): array => [
                'slug' => $league->value,
                'label' => $league->label(),
                'image' => $league->image(),
                'subscribed' => in_array($league->value, $subscribedLeagues, true),
            ], LolLeague::cases()),
        ]);
    }

    public function toggleLeague(LolLeague $league): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $subscription = $user->lolLeagueSubscriptions()->firstOrCreate(['league' => $league]);

        if ($subscription->wasRecentlyCreated) {
            SyncLolScheduleJob::dispatch();
        } else {
            $subscription->delete();
        }

        return back(status: 303);
    }
}
