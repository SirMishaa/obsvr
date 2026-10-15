# LoL Esports Notifications — Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users pick All/Teams per followed league with team chips, set a co-streamer channel, configure the reminder delay in Settings, and see upcoming matches on `/lol`.

**Architecture:** Backend for filtering/co-stream/reminder already exists (phase 1: `LolLeagueSubscription::matches()/watchUrl()`, `users.lol_reminder_minutes`). Phase 2 adds: richer `/lol` props (per-league settings, available teams, upcoming matches with a "will notify" flag), a `PATCH /lol/leagues/{league}` endpoint, a `settings/lol` page, and Vue components `LolLeagueCard` + `LolMatchRow`.

**Tech Stack:** Laravel 13, Pest 5, Inertia v3 + Vue 3 + Wayfinder (`formVariants`), reka-ui based `@/components/ui/*`.

**Spec:** `docs/superpowers/specs/2026-10-15-lol-esports-notifications-design.md` (sections UI, Phases → Phase 2)

## Global Constraints

- Reminder options: `off` (null), 5, 10, 15, 20, 30, 60 minutes. Default 20.
- Co-streamer channel = Twitch login: `^[A-Za-z0-9_]{3,25}$`, nullable.
- `team_codes` required (min 1) when `mode = teams`, each a team code string ≤ 10 chars; stored `null` in `all` mode.
- Team chips come from `lol_matches` of that league, distinct by code, `TBD` excluded, sorted by code.
- Upcoming list: subscribed leagues, `state != completed`, `start_time` from now − 4 h to now + 7 days, max 40, ordered by start.
- Times shown in `Europe/Brussels`. Match ids stay strings.
- PHP strict types, final classes, 100 % type coverage, PHPStan max clean (no baseline additions). Run rector + pint before commit.
- `bun`/`bunx` only. After frontend changes: `bun run build && bun run test:lint && bun run test:types`.

## Review Focus

1. User switches a league to Teams mode without picking a team → validation error, subscription unchanged. Test in Task 1.
2. User updates settings of a league they don't follow → 404, nothing created. Test in Task 1.
3. Co-streamer typed as a full URL (`https://twitch.tv/skyart`) → rejected with a validation error rather than producing a broken link. Test in Task 1.
4. Unfollowing a league removes its matches from the upcoming list on next render. Test in Task 1.
5. Reminder set to "off" persists as `null` (no reminders sent). Test in Task 3.

---

### Task 1: `/lol` backend — props and league settings endpoint

**Files:**
- Modify: `app/Http/Controllers/LolController.php`, `routes/web.php`, `tests/Feature/Lol/LolControllerTest.php`
- Create: `app/Http/Requests/UpdateLolLeagueSubscriptionRequest.php`

**Interfaces:**
- Consumes: `LolLeague`, `LolSubscriptionMode`, `LolLeagueSubscription::matches()`, `LolMatch` (phase 1).
- Produces:
  - Inertia `Lol` props:
    - `leagues: list<{slug, label, image, subscribed: bool, mode: 'all'|'teams', teamCodes: list<string>, costreamChannel: string|null, teams: list<{code, name, image}>}>`
    - `upcomingMatches: list<{id: string, league: string, leagueLabel: string, startTime: string (ISO 8601 UTC), state: string, blockName: string|null, bestOf: int|null, teams: list<{code, name, image}>, notified: bool}>`
  - Route `PATCH /lol/leagues/{league}` name `lol.leagues.update`, body `{mode: 'all'|'teams', team_codes?: list<string>, costream_channel?: string|null}` → 303 back.

- [ ] **Step 1: Write failing tests** — append to `tests/Feature/Lol/LolControllerTest.php`:

```php
test('page exposes league settings, available teams and upcoming matches', function (): void {
    $this->travelTo('2026-10-15 12:00:00');
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create(['costream_channel' => 'skyart']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => '1']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-16 18:00:00'))->create([
        'id' => '2',
        'teams' => [
            ['name' => 'T1', 'code' => 'T1', 'image' => 'https://static.lolesports.com/teams/t1.png'],
            ['name' => 'TBD', 'code' => 'TBD', 'image' => ''],
        ],
    ]);
    LolMatch::factory()->startingAt(now()->parse('2026-10-30 18:00:00'))->create(['id' => '3']);
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => '4', 'league' => LolLeague::Lck]);

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('leagues.0.mode', 'teams')
            ->where('leagues.0.teamCodes', ['KC'])
            ->where('leagues.0.costreamChannel', 'skyart')
            ->where('leagues.0.teams', [
                ['code' => 'C9', 'name' => 'Cloud9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
                ['code' => 'KC', 'name' => 'Karmine Corp', 'image' => 'https://static.lolesports.com/teams/kc.png'],
                ['code' => 'T1', 'name' => 'T1', 'image' => 'https://static.lolesports.com/teams/t1.png'],
            ])
            ->where('leagues.1.teams', [])
            ->has('upcomingMatches', 2)
            ->where('upcomingMatches.0.id', '1')
            ->where('upcomingMatches.0.startTime', '2026-10-15T18:00:00Z')
            ->where('upcomingMatches.0.leagueLabel', 'Worlds')
            ->where('upcomingMatches.0.notified', true)
            ->where('upcomingMatches.1.id', '2')
            ->where('upcomingMatches.1.notified', false));
});

test('unfollowed leagues have no upcoming matches', function (): void {
    $user = User::factory()->create();
    LolMatch::factory()->create();

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->has('upcomingMatches', 0));
});

test('league settings can switch to followed teams with a co-streamer', function (): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), [
            'mode' => 'teams',
            'team_codes' => ['KC', 'T1'],
            'costream_channel' => 'skyart',
        ])
        ->assertRedirect();

    $subscription->refresh();
    expect($subscription->mode)->toBe(LolSubscriptionMode::Teams)
        ->and($subscription->team_codes)->toBe(['KC', 'T1'])
        ->and($subscription->costream_channel)->toBe('skyart');
});

test('switching back to all clears team codes', function (): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->teams(['KC'])->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), ['mode' => 'all', 'costream_channel' => null])
        ->assertRedirect();

    expect($subscription->refresh()->team_codes)->toBeNull()
        ->and($subscription->mode)->toBe(LolSubscriptionMode::All);
});

test('league settings validation', function (array $payload, string $errorKey): void {
    $user = User::factory()->create();
    $subscription = LolLeagueSubscription::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), $payload)
        ->assertSessionHasErrors($errorKey);

    expect($subscription->refresh()->mode)->toBe(LolSubscriptionMode::All);
})->with([
    'teams mode without team' => [['mode' => 'teams', 'team_codes' => []], 'team_codes'],
    'unknown mode' => [['mode' => 'favourites'], 'mode'],
    'costream as url' => [['mode' => 'all', 'costream_channel' => 'https://twitch.tv/skyart'], 'costream_channel'],
]);

test('settings of a league that is not followed are not found', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol.leagues.update', 'worlds'), ['mode' => 'all'])
        ->assertNotFound();

    expect($user->lolLeagueSubscriptions()->exists())->toBeFalse();
});
```

Add imports at the top of the file: `use App\Enums\LolSubscriptionMode;`, `use App\Models\LolMatch;`.

Also update the existing `page lists leagues with subscription state` test: its `where('leagues.0', [...])` exact-array assertion now needs the new keys — replace it with:

```php
            ->where('leagues.0.slug', 'worlds')
            ->where('leagues.0.label', 'Worlds')
            ->where('leagues.0.image', LolLeague::Worlds->image())
            ->where('leagues.0.subscribed', true)
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolControllerTest.php`
Expected: FAIL — missing props / route `lol.leagues.update` not defined.

- [ ] **Step 3: Implement**

`app/Http/Requests/UpdateLolLeagueSubscriptionRequest.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\LolSubscriptionMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateLolLeagueSubscriptionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::enum(LolSubscriptionMode::class)],
            'team_codes' => ['exclude_unless:mode,teams', 'required', 'array', 'min:1', 'max:20'],
            'team_codes.*' => ['string', 'max:10', 'distinct'],
            'costream_channel' => ['nullable', 'string', 'regex:/^[A-Za-z0-9_]{3,25}$/'],
        ];
    }
}
```

`app/Http/Controllers/LolController.php` — replace `index()` and add `updateLeague()` (keep `toggleLeague()`):

```php
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
                    'mode' => ($subscription?->mode ?? LolSubscriptionMode::All)->value,
                    'teamCodes' => $subscription?->team_codes ?? [],
                    'costreamChannel' => $subscription?->costream_channel,
                    'teams' => $this->teamsOf($league),
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
     * @return list<array{code: string, name: string, image: string}>
     */
    private function teamsOf(LolLeague $league): array
    {
        return LolMatch::query()
            ->where('league', $league)
            ->get()
            ->flatMap(fn (LolMatch $match): array => $match->teams)
            ->reject(fn (array $team): bool => $team['code'] === 'TBD')
            ->unique('code')
            ->sortBy('code')
            ->map(fn (array $team): array => ['code' => $team['code'], 'name' => $team['name'], 'image' => $team['image']])
            ->values()
            ->all();
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

        return LolMatch::query()
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
            ->values()
            ->all();
    }
```

Imports to add in the controller: `App\Enums\LolSubscriptionMode`, `App\Http\Requests\UpdateLolLeagueSubscriptionRequest`, `App\Models\LolLeagueSubscription` (already), `App\Models\LolMatch`, `App\Services\LolMatchWatcher`, `Illuminate\Database\Eloquent\Collection`.

`routes/web.php` — inside the `lol` group, add:

```php
    Route::patch('leagues/{league}', [LolController::class, 'updateLeague'])->name('lol.leagues.update');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolControllerTest.php`
Expected: PASS. If `startTime` serialization differs (e.g. `+00:00`), fix the controller to `toIso8601ZuluString()` — the test value is the contract.

- [ ] **Step 5: Rector + Pint + PHPStan + commit**

```bash
vendor/bin/rector process app tests && vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=2G
git add app/Http/Controllers/LolController.php app/Http/Requests/UpdateLolLeagueSubscriptionRequest.php routes/web.php tests/Feature/Lol/LolControllerTest.php
git commit -m "feat(lol): expose league settings, teams and upcoming matches"
```

---

### Task 2: `/lol` frontend — league cards and upcoming matches

**Files:**
- Create: `resources/js/types/lol.ts`, `resources/js/components/lol/LolLeagueCard.vue`, `resources/js/components/lol/LolMatchRow.vue`
- Modify: `resources/js/pages/Lol.vue`

**Interfaces:**
- Consumes: Inertia props from Task 1; Wayfinder `toggle`, `update` from `@/routes/lol/leagues` (regenerated by `php artisan wayfinder:generate --with-form --no-interaction`).
- Produces: UI only.

- [ ] **Step 1: Types** — `resources/js/types/lol.ts`:

```ts
export type LolTeam = {
    code: string;
    name: string;
    image: string;
};

export type LolLeague = {
    slug: string;
    label: string;
    image: string;
    subscribed: boolean;
    mode: 'all' | 'teams';
    teamCodes: string[];
    costreamChannel: string | null;
    teams: LolTeam[];
};

export type LolUpcomingMatch = {
    id: string;
    league: string;
    leagueLabel: string;
    startTime: string;
    state: string;
    blockName: string | null;
    bestOf: number | null;
    teams: LolTeam[];
    notified: boolean;
};
```

- [ ] **Step 2: League card** — `resources/js/components/lol/LolLeagueCard.vue`:

```vue
<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Bell, BellOff } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toggle, update } from '@/routes/lol/leagues';
import type { LolLeague } from '@/types/lol';

const props = defineProps<{ league: LolLeague }>();

const form = useForm({
    mode: props.league.mode,
    team_codes: [...props.league.teamCodes],
    costream_channel: props.league.costreamChannel ?? '',
});

const toggleFollow = () => {
    router.post(toggle.url(props.league.slug), {}, { preserveScroll: true });
};

const toggleTeam = (code: string) => {
    form.team_codes = form.team_codes.includes(code)
        ? form.team_codes.filter((teamCode) => teamCode !== code)
        : [...form.team_codes, code];
};

const save = () => {
    form.submit(update(props.league.slug), { preserveScroll: true });
};
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
    >
        <div class="flex items-center gap-4">
            <img
                :src="league.image"
                :alt="league.label"
                class="size-12 rounded-md bg-neutral-900 object-contain p-1"
            />
            <span class="flex-1 font-medium">{{ league.label }}</span>
            <Button
                :variant="league.subscribed ? 'default' : 'outline'"
                size="sm"
                @click="toggleFollow"
            >
                <Bell v-if="league.subscribed" class="size-4" />
                <BellOff v-else class="size-4" />
                {{ league.subscribed ? 'Following' : 'Follow' }}
            </Button>
        </div>

        <form
            v-if="league.subscribed"
            class="flex flex-col gap-4"
            @submit.prevent="save"
        >
            <div class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="form.mode === 'all' ? 'default' : 'outline'"
                    @click="form.mode = 'all'"
                >
                    All matches
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="form.mode === 'teams' ? 'default' : 'outline'"
                    @click="form.mode = 'teams'"
                >
                    My teams
                </Button>
            </div>

            <div v-if="form.mode === 'teams'" class="flex flex-col gap-2">
                <p
                    v-if="league.teams.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    Teams appear once the schedule is synced.
                </p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="team in league.teams"
                        :key="team.code"
                        type="button"
                        :title="team.name"
                        class="flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors"
                        :class="
                            form.team_codes.includes(team.code)
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border hover:bg-accent'
                        "
                        @click="toggleTeam(team.code)"
                    >
                        <img
                            v-if="team.image"
                            :src="team.image"
                            :alt="team.name"
                            class="size-4 object-contain"
                        />
                        {{ team.code }}
                    </button>
                </div>
                <InputError :message="form.errors.team_codes" />
            </div>

            <div class="grid gap-2">
                <Label :for="`costream-${league.slug}`"
                    >Co-streamer (Twitch)</Label
                >
                <Input
                    :id="`costream-${league.slug}`"
                    v-model="form.costream_channel"
                    placeholder="e.g. skyart"
                    autocomplete="off"
                />
                <InputError :message="form.errors.costream_channel" />
            </div>

            <div class="flex items-center gap-4">
                <Button type="submit" size="sm" :disabled="form.processing"
                    >Save</Button
                >
                <p
                    v-show="form.recentlySuccessful"
                    class="text-sm text-neutral-600"
                >
                    Saved.
                </p>
            </div>
        </form>
    </div>
</template>
```

- [ ] **Step 3: Match row** — `resources/js/components/lol/LolMatchRow.vue`:

```vue
<script setup lang="ts">
import { Bell, BellOff } from 'lucide-vue-next';
import { computed } from 'vue';
import type { LolUpcomingMatch } from '@/types/lol';

const props = defineProps<{ match: LolUpcomingMatch }>();

const startsAt = computed(() =>
    new Intl.DateTimeFormat('fr-BE', {
        timeZone: 'Europe/Brussels',
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.match.startTime)),
);

const details = computed(() =>
    [
        props.match.leagueLabel,
        props.match.blockName,
        props.match.bestOf ? `BO${props.match.bestOf}` : null,
    ]
        .filter(Boolean)
        .join(' · '),
);
</script>

<template>
    <li
        class="flex items-center gap-4 rounded-lg border border-sidebar-border/70 px-4 py-3 dark:border-sidebar-border"
    >
        <span class="w-28 shrink-0 text-sm text-muted-foreground tabular-nums">
            {{ startsAt }}
        </span>
        <div class="flex flex-1 items-center gap-3">
            <template v-for="(team, index) in match.teams" :key="team.code">
                <span v-if="index > 0" class="text-xs text-muted-foreground"
                    >vs</span
                >
                <span class="flex items-center gap-1.5 font-medium">
                    <img
                        v-if="team.image"
                        :src="team.image"
                        :alt="team.name"
                        class="size-6 object-contain"
                    />
                    {{ team.code }}
                </span>
            </template>
        </div>
        <span class="hidden text-xs text-muted-foreground sm:inline">{{
            details
        }}</span>
        <span
            v-if="match.state === 'inProgress'"
            class="rounded bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white"
            >LIVE</span
        >
        <Bell
            v-if="match.notified"
            class="size-4 text-primary"
            aria-label="You will be notified"
        />
        <BellOff
            v-else
            class="size-4 text-muted-foreground/50"
            aria-label="Not notified"
        />
    </li>
</template>
```

- [ ] **Step 4: Page** — replace `resources/js/pages/Lol.vue`:

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LolLeagueCard from '@/components/lol/LolLeagueCard.vue';
import LolMatchRow from '@/components/lol/LolMatchRow.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import type { LolLeague, LolUpcomingMatch } from '@/types/lol';

defineProps<{
    leagues: LolLeague[];
    upcomingMatches: LolUpcomingMatch[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'League of Legends', href: '/lol' },
];
</script>

<template>
    <Head title="League of Legends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4">
            <p class="text-sm text-muted-foreground">
                Follow a league to get a reminder before each match and a
                notification when it starts. The reminder delay is set in
                Settings.
            </p>
            <div class="grid items-start gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <LolLeagueCard
                    v-for="league in leagues"
                    :key="`${league.slug}-${league.subscribed}`"
                    :league="league"
                />
            </div>

            <section class="flex flex-col gap-3">
                <h2 class="text-lg font-semibold">Upcoming matches</h2>
                <p
                    v-if="upcomingMatches.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No upcoming match in the leagues you follow.
                </p>
                <ul v-else class="flex flex-col gap-2">
                    <LolMatchRow
                        v-for="match in upcomingMatches"
                        :key="match.id"
                        :match="match"
                    />
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
```

(The card `:key` includes `subscribed` so the form state resets from fresh props after follow/unfollow.)

- [ ] **Step 5: Verify frontend**

Run: `php artisan wayfinder:generate --with-form --no-interaction && bun run build && bun run test:lint && bun run test:types`
Expected: all succeed. Then `php artisan test --compact tests/Feature/Lol/LolControllerTest.php` → PASS.

- [ ] **Step 6: Commit**

```bash
bun run lint
git add resources/js/types/lol.ts resources/js/components/lol resources/js/pages/Lol.vue
git commit -m "feat(lol): team filter, co-streamer and upcoming matches on the LoL page"
```

---

### Task 3: Settings › League of Legends (reminder delay)

**Files:**
- Create: `app/Http/Controllers/Settings/LolSettingsController.php`, `resources/js/pages/settings/Lol.vue`, `tests/Feature/Settings/LolSettingsTest.php`
- Modify: `routes/settings.php`, `resources/js/layouts/settings/Layout.vue`

**Interfaces:**
- Consumes: `users.lol_reminder_minutes` (phase 1).
- Produces: routes `GET settings/lol` name `lol-settings.edit` (Inertia `settings/Lol`, prop `reminderMinutes: int|null`, `reminderOptions: list<int>`), `PATCH settings/lol` name `lol-settings.update` body `{reminder_minutes: int|null}`.

- [ ] **Step 1: Write failing tests** — `tests/Feature/Settings/LolSettingsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('lol settings page shows the reminder delay', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('lol-settings.edit'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('settings/Lol')
            ->where('reminderMinutes', 20)
            ->where('reminderOptions', [5, 10, 15, 20, 30, 60]));
});

test('reminder delay can be changed', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => 30])
        ->assertRedirect();

    expect($user->refresh()->lol_reminder_minutes)->toBe(30);
});

test('reminder can be turned off', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => null])
        ->assertRedirect();

    expect($user->refresh()->lol_reminder_minutes)->toBeNull();
});

test('reminder delay must be one of the options', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('lol-settings.update'), ['reminder_minutes' => 7])
        ->assertSessionHasErrors('reminder_minutes');

    expect($user->refresh()->lol_reminder_minutes)->toBe(20);
});

test('guests cannot see lol settings', function (): void {
    $this->get(route('lol-settings.edit'))->assertRedirect(route('login'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Settings/LolSettingsTest.php`
Expected: FAIL — route `lol-settings.edit` not defined.

- [ ] **Step 3: Implement backend**

`app/Http/Controllers/Settings/LolSettingsController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class LolSettingsController extends Controller
{
    /**
     * @var list<int>
     */
    private const array REMINDER_OPTIONS = [5, 10, 15, 20, 30, 60];

    public function edit(): Response
    {
        /** @var User $user */
        $user = auth()->user();

        return Inertia::render('settings/Lol', [
            'reminderMinutes' => $user->lol_reminder_minutes,
            'reminderOptions' => self::REMINDER_OPTIONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'reminder_minutes' => ['present', 'nullable', 'integer', Rule::in(self::REMINDER_OPTIONS)],
        ]);

        /** @var User $user */
        $user = auth()->user();
        $user->update(['lol_reminder_minutes' => $validated['reminder_minutes']]);

        return back(status: 303);
    }
}
```

`app/Models/User.php` — add the in-memory default so freshly created users (e.g. `actingAs` instances, which never re-read the DB default) expose 20 instead of `null`:

```php
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'lol_reminder_minutes' => 20,
    ];
```

`routes/settings.php` — add the import `use App\Http\Controllers\Settings\LolSettingsController;` and inside the auth group:

```php
    Route::get('settings/lol', [LolSettingsController::class, 'edit'])->name('lol-settings.edit');
    Route::patch('settings/lol', [LolSettingsController::class, 'update'])->name('lol-settings.update');
```

- [ ] **Step 4: Implement frontend**

`resources/js/pages/settings/Lol.vue`:

```vue
<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Select, SelectItem } from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { update } from '@/routes/lol-settings';
import { type BreadcrumbItem } from '@/types';

const props = defineProps<{
    reminderMinutes: number | null;
    reminderOptions: number[];
}>();

const breadcrumbItems: BreadcrumbItem[] = [
    { title: 'League of Legends settings', href: '/settings/lol' },
];

const OFF = 'off';

const form = useForm({
    reminder_minutes: props.reminderMinutes,
});

const selected = computed({
    get: () =>
        form.reminder_minutes === null ? OFF : String(form.reminder_minutes),
    set: (value: string) => {
        form.reminder_minutes = value === OFF ? null : Number(value);
    },
});

const save = () => {
    form.submit(update(), { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="League of Legends settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="League of Legends"
                    description="Choose when to be reminded before a followed match starts"
                />

                <form class="space-y-6" @submit.prevent="save">
                    <div class="grid max-w-xs gap-2">
                        <Label>Reminder before a match</Label>
                        <Select v-model="selected">
                            <SelectItem :value="OFF">No reminder</SelectItem>
                            <SelectItem
                                v-for="minutes in reminderOptions"
                                :key="minutes"
                                :value="String(minutes)"
                                >{{ minutes }} minutes</SelectItem
                            >
                        </Select>
                        <InputError :message="form.errors.reminder_minutes" />
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Save</Button>
                        <p
                            v-show="form.recentlySuccessful"
                            class="text-sm text-neutral-600"
                        >
                            Saved.
                        </p>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
```

`resources/js/layouts/settings/Layout.vue` — import `import { edit as editLolSettings } from '@/routes/lol-settings';` and append to `sidebarNavItems`:

```ts
    {
        title: 'League of Legends',
        href: editLolSettings.url(),
    },
```

- [ ] **Step 5: Verify**

Run: `php artisan wayfinder:generate --with-form --no-interaction && bun run build && bun run test:lint && bun run test:types && php artisan test --compact tests/Feature/Settings/LolSettingsTest.php`
Expected: all succeed, 5 tests PASS.

- [ ] **Step 6: Commit**

```bash
vendor/bin/rector process app tests && vendor/bin/pint --dirty --format agent && bun run lint
git add app/Http/Controllers/Settings/LolSettingsController.php routes/settings.php resources/js/pages/settings/Lol.vue resources/js/layouts/settings/Layout.vue tests/Feature/Settings/LolSettingsTest.php
git commit -m "feat(lol): reminder delay setting"
```

---

### Task 4: Full verification and browser smoke

- [ ] **Step 1:** `composer test` → exit 0 (type coverage 100 %, PHPStan 0, lint clean).
- [ ] **Step 2:** Add to `tests/Browser/SmokeTest.php` the authenticated pages `/lol` and `/settings/lol` in the same style as the existing settings smoke checks (visit, `assertNoJavaScriptErrors()`), run `php artisan test --compact tests/Browser/SmokeTest.php` → PASS.
- [ ] **Step 3:** Manual check locally: `php artisan lol:sync-schedule` (after following Worlds), open `/lol`, switch Worlds to "My teams", pick KC, set co-streamer `skyart`, save; upcoming list shows 🔔 only on KC matches.
- [ ] **Step 4:** Commit any fix: `git commit -m "test(lol): smoke-test LoL pages"`.
