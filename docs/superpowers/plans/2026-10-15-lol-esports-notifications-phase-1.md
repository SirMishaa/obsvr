# LoL Esports Notifications — Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Web-push a reminder before, and a notification at the real start of, LoL esports matches of the leagues a user follows (phase 1: backend complete, minimal `/lol` page with per-league toggle).

**Architecture:** An hourly scheduled command syncs `getSchedule` per subscribed league into `lol_matches`, and when a match is ≤ 90 min away dispatches one global self-re-dispatching `LolTickJob` (`ShouldBeUniqueUntilProcessing`). The tick delegates to `LolMatchWatcher`, which refreshes matches, reads `getLive`, sends reminders/started notifications deduplicated via `insertOrIgnore` on `lol_match_notifications`, and returns the delay (60 s–15 min) until the next tick, or `null` to end the chain.

**Tech Stack:** Laravel 13, PHP 8.5 (strict types, final classes — Pint config), Pest 5, Inertia v3 + Vue 3 + Wayfinder, `laravel-notification-channels/webpush`.

**Spec:** `docs/superpowers/specs/2026-10-15-lol-esports-notifications-design.md`

## Global Constraints

- API base `https://esports-api.lolesports.com/persisted/gw`, header `x-api-key: 0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z`, param `hl=en-GB`.
- Only `getSchedule` (one call per league, follow `pages.newer`) and `getLive` are used. Never `getEventDetails`.
- Match ids are 18-digit strings everywhere (`$incrementing = false`, `$keyType = 'string'`).
- `http://static.lolesports.com` images rewritten to `https://`.
- Leagues: worlds `98767975604431411`, msi `98767991325878492`, first_stand `113464388705111224`, lck `98767991310872058`, lec `98767991302996019`.
- Tick delay clamped to `[60, 900]` seconds; from `start_time − 2 min` it is 60 s; abandon after `start_time + 4 h`; watch window `start_time ≤ now + 90 min`.
- Reminder default `lol_reminder_minutes = 20`, `null` = no reminder; reminder skipped if < 1 min remains; minutes shown = `ceil(remaining)`.
- Dedup: `insertOrIgnore` on `lol_match_notifications` before sending; send only if a row was inserted.
- Never use `ShouldBeUnique` on the tick (re-dispatch from `handle()` would be dropped).
- HTTP errors in the tick: no `release()`; catch, log, re-dispatch with 60 s delay.
- Strings in `lang/en/lol.php`. Times displayed in `Europe/Brussels`.
- PHP: `declare(strict_types=1)`, `final` classes, typed params/returns (type coverage 100 %). Run `vendor/bin/pint --dirty --format agent` after PHP edits.
- Tests: `php artisan test --compact --parallel <path>`. `tests/Pest.php` already freezes time, prevents stray HTTP.
- Use `bun`/`bunx`, never `npm`/`npx`.

## Review Focus

1. Match postponed after the reminder was sent → reminder must not be re-sent (dedup row exists) and the start notification still fires on the real start. Test in Task 4.
2. User subscribes while a match is already `inProgress` → gets one "started" push on the next tick, never a reminder. Test in Task 4.
3. Two ticks running the same minute (at-least-once delivery) → exactly one push per user/type. Test in Task 4.
4. lolesports API down during a tick → chain survives (re-dispatch at 60 s), no exception escapes. Test in Task 5.
5. Match with `TBD` teams in `teams` mode → never matches a team filter. Test in Task 2.

---

## File Structure

| File | Responsibility |
| - | - |
| `app/Enums/LolLeague.php` | League catalogue (slug, API id, label, logo) |
| `app/Enums/LolSubscriptionMode.php` | `all` / `teams` |
| `app/Enums/LolNotificationType.php` | `reminder` / `started` |
| `app/Data/LolMatchData.php` | Parsed match from API + DB row mapping |
| `app/Services/LolEsportsClient.php` | HTTP calls `schedule()`, `live()` |
| `app/Models/LolMatch.php`, `LolLeagueSubscription.php`, `LolMatchNotification.php` | Persistence + small domain helpers |
| `database/migrations/*_create_lol_esports_tables.php` | 3 tables + `users.lol_reminder_minutes` |
| `app/Services/LolMatchWatcher.php` | One tick of logic, returns next delay |
| `app/Jobs/SyncLolScheduleJob.php`, `app/Jobs/LolTickJob.php` | Queue entry points |
| `app/Console/Commands/SyncLolSchedule.php` | `lol:sync-schedule`, scheduled hourly |
| `app/Notifications/LolMatchReminderNotification.php`, `LolMatchStartedNotification.php` | WebPush payloads |
| `app/Http/Controllers/LolController.php` + `resources/js/pages/Lol.vue` | Minimal page + toggle |

---

### Task 1: Enums, DTO and API client

**Files:**
- Create: `app/Enums/LolLeague.php`, `app/Enums/LolSubscriptionMode.php`, `app/Enums/LolNotificationType.php`, `app/Data/LolMatchData.php`, `app/Services/LolEsportsClient.php`, `tests/Mocks/LolEsportsResponses.php`, `tests/Feature/Lol/LolEsportsClientTest.php`
- Modify: `config/services.php` (add `lolesports` block)

**Interfaces:**
- Produces:
  - `enum LolLeague: string { Worlds='worlds', Msi='msi', FirstStand='first_stand', Lck='lck', Lec='lec' }` with `leagueId(): string`, `label(): string`, `image(): string`.
  - `enum LolSubscriptionMode: string { All='all', Teams='teams' }`, `enum LolNotificationType: string { Reminder='reminder', Started='started' }`.
  - `final readonly class LolMatchData(string $id, LolLeague $league, CarbonImmutable $startTime, string $state, ?string $blockName, ?int $bestOf, array $teams)`; `static fromScheduleEvent(array $event, LolLeague $league): self`; `toRow(): array<string, mixed>`. `$teams` is `list<array{name: string, code: string, image: string}>`.
  - `LolEsportsClient::schedule(LolLeague $league): list<LolMatchData>`, `LolEsportsClient::live(): list<string>` (match ids in progress). Both throw `Illuminate\Http\Client\RequestException|ConnectionException` on failure.
  - `Tests\Mocks\LolEsportsResponses::MATCH_ID` (`'115660588583171786'`), `::scheduleEvent(array $overrides = []): array`, `::schedule(array $events, ?string $newer = null): array`, `::live(array $matchIds): array`, `::fake(string $state = 'unstarted', string $startTime = '2026-10-15T18:00:00Z', array $liveIds = []): void`.

- [ ] **Step 1: Write config and enums**

`config/services.php` — add before the closing `];`:

```php
    'lolesports' => [
        'base_url' => env('LOLESPORTS_BASE_URL', 'https://esports-api.lolesports.com/persisted/gw'),
        'api_key' => env('LOLESPORTS_API_KEY', '0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z'),
    ],
```

`app/Enums/LolLeague.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum LolLeague: string
{
    case Worlds = 'worlds';
    case Msi = 'msi';
    case FirstStand = 'first_stand';
    case Lck = 'lck';
    case Lec = 'lec';

    public function leagueId(): string
    {
        return match ($this) {
            self::Worlds => '98767975604431411',
            self::Msi => '98767991325878492',
            self::FirstStand => '113464388705111224',
            self::Lck => '98767991310872058',
            self::Lec => '98767991302996019',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Worlds => 'Worlds',
            self::Msi => 'MSI',
            self::FirstStand => 'First Stand',
            self::Lck => 'LCK',
            self::Lec => 'LEC',
        };
    }

    public function image(): string
    {
        return match ($this) {
            self::Worlds => 'https://static.lolesports.com/leagues/1592594612171_WorldsDarkBG.png',
            self::Msi => 'https://static.lolesports.com/leagues/1592594634248_MSIDarkBG.png',
            self::FirstStand => 'https://static.lolesports.com/leagues/1740042025201_RG_LOL_FIRST_STAND_LOGO_VOLT_ALPHA.png',
            self::Lck => 'https://static.lolesports.com/leagues/lck-color-on-black.png',
            self::Lec => 'https://static.lolesports.com/leagues/1592516184297_LEC-01-FullonDark.png',
        };
    }
}
```

`app/Enums/LolSubscriptionMode.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum LolSubscriptionMode: string
{
    case All = 'all';
    case Teams = 'teams';
}
```

`app/Enums/LolNotificationType.php`:

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum LolNotificationType: string
{
    case Reminder = 'reminder';
    case Started = 'started';
}
```

- [ ] **Step 2: Write test fixtures**

`tests/Mocks/LolEsportsResponses.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Mocks;

use Illuminate\Support\Facades\Http;

final class LolEsportsResponses
{
    public const string MATCH_ID = '115660588583171786';

    /**
     * Fake getSchedule (one match with given state/start) and getLive (given ids in progress).
     *
     * @param  list<string>  $liveIds
     */
    public static function fake(string $state = 'unstarted', string $startTime = '2026-10-15T18:00:00Z', array $liveIds = []): void
    {
        Http::fake([
            '*/getSchedule*' => Http::response(self::schedule([
                self::scheduleEvent(['state' => $state, 'startTime' => $startTime]),
            ])),
            '*/getLive*' => Http::response(self::live($liveIds)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function scheduleEvent(array $overrides = []): array
    {
        return array_replace_recursive([
            'startTime' => '2026-10-15T18:00:00Z',
            'state' => 'unstarted',
            'type' => 'match',
            'blockName' => 'Play-Ins',
            'league' => ['name' => 'Worlds', 'slug' => 'worlds'],
            'match' => [
                'id' => '115660588583171786',
                'flags' => [],
                'teams' => [
                    ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'http://static.lolesports.com/teams/c9.png', 'result' => null, 'record' => null],
                    ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'http://static.lolesports.com/teams/kc.png', 'result' => null, 'record' => null],
                ],
                'strategy' => ['type' => 'bestOf', 'count' => 5],
            ],
        ], $overrides);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public static function schedule(array $events, ?string $newer = null): array
    {
        return ['data' => ['schedule' => ['pages' => ['older' => null, 'newer' => $newer], 'events' => $events]]];
    }

    /**
     * @param  list<string>  $matchIds
     * @return array<string, mixed>
     */
    public static function live(array $matchIds): array
    {
        return ['data' => ['schedule' => ['events' => array_map(
            fn (string $id): array => self::scheduleEvent(['state' => 'inProgress', 'match' => ['id' => $id]]),
            $matchIds,
        )]]];
    }
}
```

- [ ] **Step 3: Write the failing client tests**

`tests/Feature/Lol/LolEsportsClientTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Services\LolEsportsClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\Mocks\LolEsportsResponses;

test('schedule parses match events of a league', function (): void {
    Http::fake([
        '*/getSchedule*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(),
            LolEsportsResponses::scheduleEvent(['type' => 'show', 'match' => ['id' => '1']]),
        ])),
    ]);

    $matches = resolve(LolEsportsClient::class)->schedule(LolLeague::Worlds);

    expect($matches)->toHaveCount(1)
        ->and($matches[0]->id)->toBe('115660588583171786')
        ->and($matches[0]->league)->toBe(LolLeague::Worlds)
        ->and($matches[0]->startTime->toIso8601ZuluString())->toBe('2026-10-15T18:00:00Z')
        ->and($matches[0]->state)->toBe('unstarted')
        ->and($matches[0]->blockName)->toBe('Play-Ins')
        ->and($matches[0]->bestOf)->toBe(5)
        ->and($matches[0]->teams)->toBe([
            ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
            ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc.png'],
        ]);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', '0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z')
        && $request['leagueId'] === '98767975604431411'
        && $request['hl'] === 'en-GB');
});

test('schedule follows the newer page until it covers the next day', function (): void {
    $this->travelTo('2026-10-15 12:00:00');

    Http::fake([
        '*/getSchedule*pageToken=next*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(['startTime' => '2026-10-20T18:00:00Z', 'match' => ['id' => '2']]),
        ])),
        '*/getSchedule*' => Http::response(LolEsportsResponses::schedule([
            LolEsportsResponses::scheduleEvent(['startTime' => '2026-10-15T18:00:00Z', 'match' => ['id' => '1']]),
        ], newer: 'next')),
    ]);

    $matches = resolve(LolEsportsClient::class)->schedule(LolLeague::Worlds);

    expect(array_map(fn ($match) => $match->id, $matches))->toBe(['1', '2']);
    Http::assertSentCount(2);
});

test('live returns ids of matches in progress', function (): void {
    Http::fake(['*/getLive*' => Http::response(LolEsportsResponses::live(['111', '222']))]);

    expect(resolve(LolEsportsClient::class)->live())->toBe(['111', '222']);
});

test('client throws on api errors', function (): void {
    Http::fake(['*' => Http::response(status: 500)]);

    resolve(LolEsportsClient::class)->live();
})->throws(RequestException::class);
```

- [ ] **Step 4: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolEsportsClientTest.php`
Expected: FAIL — `Class "App\Services\LolEsportsClient" not found`.

- [ ] **Step 5: Implement DTO and client**

`app/Data/LolMatchData.php`:

```php
<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\LolLeague;
use Carbon\CarbonImmutable;

final readonly class LolMatchData
{
    /**
     * @param  list<array{name: string, code: string, image: string}>  $teams
     */
    public function __construct(
        public string $id,
        public LolLeague $league,
        public CarbonImmutable $startTime,
        public string $state,
        public ?string $blockName,
        public ?int $bestOf,
        public array $teams,
    ) {}

    /**
     * @param  array<string, mixed>  $event
     */
    public static function fromScheduleEvent(array $event, LolLeague $league): self
    {
        /** @var array{id: string, teams?: list<array<string, mixed>>, strategy?: array{count?: int}} $match */
        $match = $event['match'];

        return new self(
            id: (string) $match['id'],
            league: $league,
            startTime: CarbonImmutable::parse((string) $event['startTime'])->utc(),
            state: (string) $event['state'],
            blockName: isset($event['blockName']) ? (string) $event['blockName'] : null,
            bestOf: isset($match['strategy']['count']) ? (int) $match['strategy']['count'] : null,
            teams: array_map(fn (array $team): array => [
                'name' => (string) ($team['name'] ?? 'TBD'),
                'code' => (string) ($team['code'] ?? 'TBD'),
                'image' => (string) preg_replace('#^http://#', 'https://', (string) ($team['image'] ?? '')),
            ], array_values($match['teams'] ?? [])),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toRow(): array
    {
        return [
            'id' => $this->id,
            'league' => $this->league->value,
            'start_time' => $this->startTime->toDateTimeString(),
            'state' => $this->state,
            'block_name' => $this->blockName,
            'best_of' => $this->bestOf,
            'teams' => json_encode($this->teams, JSON_THROW_ON_ERROR),
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
```

`app/Services/LolEsportsClient.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final readonly class LolEsportsClient
{
    private const int MAX_SCHEDULE_PAGES = 3;

    /**
     * @return list<LolMatchData>
     */
    public function schedule(LolLeague $league): array
    {
        $matches = [];
        $pageToken = null;

        for ($page = 0; $page < self::MAX_SCHEDULE_PAGES; $page++) {
            /** @var array{pages?: array{newer?: string|null}, events?: list<array<string, mixed>>} $schedule */
            $schedule = $this->request()
                ->get('/getSchedule', array_filter([
                    'hl' => 'en-GB',
                    'leagueId' => $league->leagueId(),
                    'pageToken' => $pageToken,
                ]))
                ->json('data.schedule', []);

            foreach ($schedule['events'] ?? [] as $event) {
                if (($event['type'] ?? null) === 'match' && isset($event['match']['id'])) {
                    $matches[] = LolMatchData::fromScheduleEvent($event, $league);
                }
            }

            $pageToken = $schedule['pages']['newer'] ?? null;
            $lastMatch = end($matches);

            if ($pageToken === null || ($lastMatch !== false && $lastMatch->startTime->isAfter(now()->addDay()))) {
                break;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    public function live(): array
    {
        /** @var list<array<string, mixed>> $events */
        $events = $this->request()->get('/getLive', ['hl' => 'en-GB'])->json('data.schedule.events', []);

        $ids = [];
        foreach ($events as $event) {
            if (($event['state'] ?? null) === 'inProgress' && isset($event['match']['id'])) {
                $ids[] = (string) $event['match']['id'];
            }
        }

        return $ids;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('services.lolesports.base_url'))
            ->withHeaders(['x-api-key' => (string) config('services.lolesports.api_key')])
            ->acceptJson()
            ->timeout(10)
            ->throw();
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolEsportsClientTest.php`
Expected: PASS (4 tests).

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Enums/Lol*.php app/Data/LolMatchData.php app/Services/LolEsportsClient.php config/services.php tests/Mocks/LolEsportsResponses.php tests/Feature/Lol/LolEsportsClientTest.php
git commit -m "feat(lol): add lolesports API client"
```

---

### Task 2: Migration and models

**Files:**
- Create: migration via `php artisan make:migration create_lol_esports_tables --no-interaction`, `app/Models/LolMatch.php`, `app/Models/LolLeagueSubscription.php`, `app/Models/LolMatchNotification.php`, `database/factories/LolMatchFactory.php`, `database/factories/LolLeagueSubscriptionFactory.php`, `tests/Feature/Lol/LolModelsTest.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Consumes: `LolLeague`, `LolSubscriptionMode`, `LolNotificationType`, `LolMatchData::toRow()` (Task 1).
- Produces:
  - `LolMatch` (string PK `id`; casts `league` → `LolLeague`, `start_time` → immutable_datetime, `teams` → array, `best_of` → integer, `watch_ended_at` → immutable_datetime). Methods: `static upsertFromData(list<LolMatchData> $matches): void` (never overwrites `watch_ended_at`), `scopePending(Builder $query)` (`watch_ended_at` null, `state != completed`), `teamCodes(): list<string>`, `title(): string` ("C9 vs KC"), `summary(): string` ("Worlds · Play-Ins · BO5"), `subscriptions(): Collection<LolLeagueSubscription>` (subscriptions of the league that match this match, user eager-loaded).
  - `LolLeagueSubscription` (fillable `user_id, league, mode, team_codes, costream_channel`; casts `league`, `mode`, `team_codes` → array). Methods: `user(): BelongsTo`, `matches(LolMatch $match): bool`, `watchUrl(): string`, `watchLabel(): string`, `iconFor(LolMatch $match): string`.
  - `LolMatchNotification::claim(LolMatch $match, User $user, LolNotificationType $type): bool` — true only for the first caller.
  - `User::lolLeagueSubscriptions(): HasMany`, attribute `lol_reminder_minutes` (int|null, default 20).
  - Factories: `LolMatch::factory()` (state `startingAt(CarbonInterface $at)`), `LolLeagueSubscription::factory()` (state `teams(array $codes)`).

- [ ] **Step 1: Create migration**

Run: `php artisan make:migration create_lol_esports_tables --no-interaction`, then replace its content with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lol_reminder_minutes')->nullable()->default(20);
        });

        Schema::create('lol_league_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('league');
            $table->string('mode')->default('all');
            $table->json('team_codes')->nullable();
            $table->string('costream_channel')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'league']);
        });

        Schema::create('lol_matches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('league')->index();
            $table->dateTime('start_time')->index();
            $table->string('state');
            $table->string('block_name')->nullable();
            $table->unsignedTinyInteger('best_of')->nullable();
            $table->json('teams');
            $table->dateTime('watch_ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lol_match_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('lol_match_id');
            $table->foreign('lol_match_id')->references('id')->on('lol_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->dateTime('sent_at');

            $table->unique(['lol_match_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lol_match_notifications');
        Schema::dropIfExists('lol_matches');
        Schema::dropIfExists('lol_league_subscriptions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('lol_reminder_minutes');
        });
    }
};
```

- [ ] **Step 2: Write failing model tests**

`tests/Feature/Lol/LolModelsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use App\Enums\LolNotificationType;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;
use Tests\Mocks\LolEsportsResponses;

test('upsert from data keeps string ids and does not reset watch_ended_at', function (): void {
    $data = LolMatchData::fromScheduleEvent(LolEsportsResponses::scheduleEvent(), LolLeague::Worlds);
    LolMatch::upsertFromData([$data]);
    LolMatch::query()->whereKey('115660588583171786')->update(['watch_ended_at' => now()]);

    LolMatch::upsertFromData([LolMatchData::fromScheduleEvent(
        LolEsportsResponses::scheduleEvent(['state' => 'inProgress']),
        LolLeague::Worlds,
    )]);

    $match = LolMatch::query()->findOrFail('115660588583171786');
    expect($match->id)->toBe('115660588583171786')
        ->and($match->state)->toBe('inProgress')
        ->and($match->watch_ended_at)->not->toBeNull()
        ->and($match->title())->toBe('C9 vs KC')
        ->and($match->summary())->toBe('Worlds · Play-Ins · BO5')
        ->and($match->teamCodes())->toBe(['C9', 'KC']);
});

test('subscription team filter', function (array $codes, bool $expected): void {
    $match = LolMatch::factory()->create();
    $subscription = LolLeagueSubscription::factory()->teams($codes)->create();

    expect($subscription->matches($match))->toBe($expected);
})->with([
    'followed team plays' => [['KC'], true],
    'no followed team' => [['T1', 'G2'], false],
    'TBD never matches' => [['TBD'], false],
]);

test('all mode matches every match of its league only', function (): void {
    $subscription = LolLeagueSubscription::factory()->create(['league' => LolLeague::Worlds]);

    expect($subscription->matches(LolMatch::factory()->create(['league' => LolLeague::Worlds])))->toBeTrue()
        ->and($subscription->matches(LolMatch::factory()->create(['league' => LolLeague::Lck])))->toBeFalse();
});

test('match subscriptions returns matching subscriptions of its league', function (): void {
    $match = LolMatch::factory()->create();
    $all = LolLeagueSubscription::factory()->create();
    LolLeagueSubscription::factory()->teams(['T1'])->create();
    LolLeagueSubscription::factory()->create(['league' => LolLeague::Lec]);

    expect($match->subscriptions()->pluck('id')->all())->toBe([$all->id]);
});

test('watch url and icon depend on costream channel and team mode', function (): void {
    $match = LolMatch::factory()->create();

    $plain = LolLeagueSubscription::factory()->create();
    expect($plain->watchUrl())->toBe('https://lolesports.com/live/worlds')
        ->and($plain->iconFor($match))->toBe(LolLeague::Worlds->image());

    $costream = LolLeagueSubscription::factory()->teams(['KC'])->create(['costream_channel' => 'skyart']);
    expect($costream->watchUrl())->toBe('https://twitch.tv/skyart')
        ->and($costream->watchLabel())->toBe('Watch on skyart')
        ->and($costream->iconFor($match))->toBe('https://static.lolesports.com/teams/kc.png');
});

test('notification claim succeeds only once', function (): void {
    $match = LolMatch::factory()->create();
    $user = User::factory()->create();

    expect(App\Models\LolMatchNotification::claim($match, $user, LolNotificationType::Reminder))->toBeTrue()
        ->and(App\Models\LolMatchNotification::claim($match, $user, LolNotificationType::Reminder))->toBeFalse()
        ->and(App\Models\LolMatchNotification::claim($match, $user, LolNotificationType::Started))->toBeTrue();
});

test('users get a 20 minutes reminder by default', function (): void {
    expect(User::factory()->create()->fresh()->lol_reminder_minutes)->toBe(20);
});
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolModelsTest.php`
Expected: FAIL — `Class "App\Models\LolMatch" not found`.

- [ ] **Step 4: Implement models and factories**

`app/Models/LolMatch.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\LolMatchData;
use App\Enums\LolLeague;
use Database\Factories\LolMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property list<array{name: string, code: string, image: string}> $teams
 */
#[Fillable([
    'id',
    'league',
    'start_time',
    'state',
    'block_name',
    'best_of',
    'teams',
    'watch_ended_at',
])]
final class LolMatch extends Model
{
    /** @use HasFactory<LolMatchFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @param  list<LolMatchData>  $matches
     */
    public static function upsertFromData(array $matches): void
    {
        if ($matches === []) {
            return;
        }

        self::query()->upsert(
            array_map(fn (LolMatchData $match): array => $match->toRow(), $matches),
            ['id'],
            ['start_time', 'state', 'block_name', 'best_of', 'teams', 'updated_at'],
        );
    }

    /**
     * @return list<string>
     */
    public function teamCodes(): array
    {
        return array_column($this->teams, 'code');
    }

    public function title(): string
    {
        return implode(' vs ', $this->teamCodes());
    }

    public function summary(): string
    {
        return implode(' · ', array_filter([
            $this->league->label(),
            $this->block_name,
            $this->best_of !== null ? 'BO'.$this->best_of : null,
        ]));
    }

    /**
     * @return Collection<int, LolLeagueSubscription>
     */
    public function subscriptions(): Collection
    {
        return LolLeagueSubscription::query()
            ->with('user')
            ->where('league', $this->league)
            ->get()
            ->filter(fn (LolLeagueSubscription $subscription): bool => $subscription->matches($this))
            ->values();
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('watch_ended_at')->where('state', '!=', 'completed');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'league' => LolLeague::class,
            'start_time' => 'immutable_datetime',
            'teams' => 'array',
            'best_of' => 'integer',
            'watch_ended_at' => 'immutable_datetime',
        ];
    }
}
```

`app/Models/LolLeagueSubscription.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use Database\Factories\LolLeagueSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property list<string>|null $team_codes
 */
#[Fillable([
    'user_id',
    'league',
    'mode',
    'team_codes',
    'costream_channel',
])]
final class LolLeagueSubscription extends Model
{
    /** @use HasFactory<LolLeagueSubscriptionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matches(LolMatch $match): bool
    {
        if ($match->league !== $this->league) {
            return false;
        }

        if ($this->mode === LolSubscriptionMode::All) {
            return true;
        }

        $followed = array_diff($this->team_codes ?? [], ['TBD']);

        return array_intersect($followed, $match->teamCodes()) !== [];
    }

    public function watchUrl(): string
    {
        return $this->costream_channel !== null
            ? 'https://twitch.tv/'.$this->costream_channel
            : 'https://lolesports.com/live/'.$this->league->value;
    }

    public function watchLabel(): string
    {
        return $this->costream_channel !== null
            ? __('lol.cta.watch_on', ['channel' => $this->costream_channel])
            : __('lol.cta.watch');
    }

    public function iconFor(LolMatch $match): string
    {
        if ($this->mode === LolSubscriptionMode::Teams) {
            foreach ($match->teams as $team) {
                if (in_array($team['code'], $this->team_codes ?? [], true) && $team['image'] !== '') {
                    return $team['image'];
                }
            }
        }

        return $this->league->image();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'league' => LolLeague::class,
            'mode' => LolSubscriptionMode::class,
            'team_codes' => 'array',
        ];
    }
}
```

`app/Models/LolMatchNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LolNotificationType;
use Illuminate\Database\Eloquent\Model;

final class LolMatchNotification extends Model
{
    public $timestamps = false;

    /**
     * Record the notification; returns false when it was already sent (at-least-once safe).
     */
    public static function claim(LolMatch $match, User $user, LolNotificationType $type): bool
    {
        return self::query()->insertOrIgnore([
            'lol_match_id' => $match->id,
            'user_id' => $user->id,
            'type' => $type->value,
            'sent_at' => now(),
        ]) === 1;
    }
}
```

`app/Models/User.php` — add `'lol_reminder_minutes'` to `#[Fillable]`, `'lol_reminder_minutes' => 'integer'` to `casts()`, and:

```php
    /**
     * @return HasMany<LolLeagueSubscription, $this>
     */
    public function lolLeagueSubscriptions(): HasMany
    {
        return $this->hasMany(LolLeagueSubscription::class);
    }
```

`database/factories/LolMatchFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LolLeague;
use App\Models\LolMatch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LolMatch>
 */
final class LolMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) fake()->unique()->numerify('11566058858######'),
            'league' => LolLeague::Worlds,
            'start_time' => now()->addHour(),
            'state' => 'unstarted',
            'block_name' => 'Play-Ins',
            'best_of' => 5,
            'teams' => [
                ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
                ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc.png'],
            ],
        ];
    }

    public function startingAt(CarbonInterface $at): static
    {
        return $this->state(fn (): array => ['start_time' => $at]);
    }
}
```

`database/factories/LolLeagueSubscriptionFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use App\Models\LolLeagueSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LolLeagueSubscription>
 */
final class LolLeagueSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'league' => LolLeague::Worlds,
            'mode' => LolSubscriptionMode::All,
            'team_codes' => null,
            'costream_channel' => null,
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    public function teams(array $codes): static
    {
        return $this->state(fn (): array => [
            'mode' => LolSubscriptionMode::Teams,
            'team_codes' => $codes,
        ]);
    }
}
```

Create `lang/en/lol.php` now (needed by `watchLabel`):

```php
<?php

declare(strict_types=1);

return [
    'match_reminder' => ':match in :minutes min',
    'match_started' => ':match has started',
    'cta' => [
        'watch' => 'Watch',
        'watch_on' => 'Watch on :channel',
    ],
];
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolModelsTest.php`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add database/migrations/*create_lol_esports_tables.php app/Models database/factories/Lol*.php lang/en/lol.php tests/Feature/Lol/LolModelsTest.php
git commit -m "feat(lol): add matches, league subscriptions and notification log"
```

---

### Task 3: Notifications

**Files:**
- Create: `app/Notifications/LolMatchReminderNotification.php`, `app/Notifications/LolMatchStartedNotification.php`, `tests/Feature/Lol/LolNotificationsTest.php`

**Interfaces:**
- Consumes: `LolMatch::title()/summary()`, `LolLeagueSubscription::watchUrl()/watchLabel()/iconFor()` (Task 2), `lang/en/lol.php`.
- Produces: `new LolMatchReminderNotification(LolMatch $match, LolLeagueSubscription $subscription, int $minutesLeft)`, `new LolMatchStartedNotification(LolMatch $match, LolLeagueSubscription $subscription)`; both `via()` → `[WebPushChannel::class]`.

- [ ] **Step 1: Write failing test**

`tests/Feature/Lol/LolNotificationsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Notifications\LolMatchReminderNotification;
use App\Notifications\LolMatchStartedNotification;

test('reminder push shows real minutes left, local start time and watch link', function (): void {
    $match = LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create();
    $subscription = LolLeagueSubscription::factory()->create(['costream_channel' => 'skyart']);

    $notification = new LolMatchReminderNotification($match, $subscription, 7);
    $message = $notification->toWebPush($subscription->user, $notification)->toArray();

    expect($message['title'])->toBe('⏰ C9 vs KC in 7 min')
        ->and($message['body'])->toBe('Worlds · Play-Ins · BO5 · 20:00')
        ->and($message['icon'])->toBe('https://static.lolesports.com/leagues/1592594612171_WorldsDarkBG.png')
        ->and($message['data'])->toBe(['url' => 'https://twitch.tv/skyart'])
        ->and($message['actions'][0])->toMatchArray(['title' => 'Watch on skyart', 'action' => 'https://twitch.tv/skyart']);
});

test('started push links to lolesports without costream', function (): void {
    $match = LolMatch::factory()->create();
    $subscription = LolLeagueSubscription::factory()->create();

    $notification = new LolMatchStartedNotification($match, $subscription);
    $message = $notification->toWebPush($subscription->user, $notification)->toArray();

    expect($message['title'])->toBe('🔴 C9 vs KC has started')
        ->and($message['body'])->toBe('Worlds · Play-Ins · BO5')
        ->and($message['data'])->toBe(['url' => 'https://lolesports.com/live/worlds']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact tests/Feature/Lol/LolNotificationsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement**

`app/Notifications/LolMatchReminderNotification.php`:

```php
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
```

`app/Notifications/LolMatchStartedNotification.php`:

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

final class LolMatchStartedNotification extends Notification
{
    public function __construct(
        public LolMatch $match,
        public LolLeagueSubscription $subscription,
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
            ->title('🔴 '.__('lol.match_started', ['match' => $this->match->title()]))
            ->body($this->match->summary())
            ->icon($this->subscription->iconFor($this->match))
            ->data(['url' => $url])
            ->action($this->subscription->watchLabel(), $url);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact tests/Feature/Lol/LolNotificationsTest.php`
Expected: PASS. If `toArray()` keys differ (`actions` shape), adjust the assertions to the real `WebPushMessage::toArray()` output, not the implementation.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Notifications/LolMatch*.php tests/Feature/Lol/LolNotificationsTest.php
git commit -m "feat(lol): add match reminder and started web push notifications"
```

---

### Task 4: Match watcher (one tick of logic)

**Files:**
- Create: `app/Services/LolMatchWatcher.php`, `tests/Feature/Lol/LolMatchWatcherTest.php`

**Interfaces:**
- Consumes: `LolEsportsClient::schedule()/live()` (Task 1), `LolMatch::upsertFromData()/pending()/subscriptions()`, `LolMatchNotification::claim()` (Task 2), notifications (Task 3).
- Produces: `LolMatchWatcher::tick(): ?int` — seconds until next tick (`60..900`) or `null` to stop. Constants `WATCH_WINDOW_MINUTES = 90`, `ABANDON_AFTER_HOURS = 4`.

- [ ] **Step 1: Write failing tests**

`tests/Feature/Lol/LolMatchWatcherTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Models\User;
use App\Notifications\LolMatchReminderNotification;
use App\Notifications\LolMatchStartedNotification;
use App\Services\LolMatchWatcher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Mocks\LolEsportsResponses;

function createWorldsMatch(): LolMatch
{
    return LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => LolEsportsResponses::MATCH_ID]);
}

beforeEach(function (): void {
    Notification::fake();
});

test('returns null when no match is within the watch window', function (): void {
    $this->travelTo('2026-10-15 15:00:00');
    createWorldsMatch();
    Http::fake();

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull();
    Http::assertNothingSent();
});

test('sends reminder once when within the user offset', function (): void {
    $this->travelTo('2026-10-15 17:41:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    $watcher = resolve(LolMatchWatcher::class);
    $watcher->tick();
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchReminderNotification::class, 1);
    Notification::assertSentTo($subscription->user, LolMatchReminderNotification::class,
        fn (LolMatchReminderNotification $notification): bool => $notification->minutesLeft === 19);
});

test('late subscriber gets reminder with real minutes left', function (): void {
    $this->travelTo('2026-10-15 17:55:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertSentTo($subscription->user, LolMatchReminderNotification::class,
        fn (LolMatchReminderNotification $notification): bool => $notification->minutesLeft === 5);
});

test('no reminder when user disabled it or less than a minute remains', function (): void {
    createWorldsMatch();
    $disabled = LolLeagueSubscription::factory()->for(User::factory()->state(['lol_reminder_minutes' => null]))->create();
    $this->travelTo('2026-10-15 17:59:30');
    $late = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertNotSentTo($disabled->user, LolMatchReminderNotification::class);
    Notification::assertNotSentTo($late->user, LolMatchReminderNotification::class);
});

test('next tick is scheduled at the earliest due moment, clamped to 15 min', function (): void {
    createWorldsMatch();
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();
    $watcher = resolve(LolMatchWatcher::class);

    $this->travelTo('2026-10-15 16:31:00');
    expect($watcher->tick())->toBe(900);

    $this->travelTo('2026-10-15 17:35:00');
    expect($watcher->tick())->toBe(300);

    $this->travelTo('2026-10-15 17:57:00');
    expect($watcher->tick())->toBe(60);
});

test('sends started notification once when live and ends the watch', function (): void {
    $this->travelTo('2026-10-15 18:03:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake(liveIds: [LolEsportsResponses::MATCH_ID]);

    $watcher = resolve(LolMatchWatcher::class);
    expect($watcher->tick())->toBeNull();
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchStartedNotification::class, 1);
    Notification::assertNotSentTo($subscription->user, LolMatchReminderNotification::class);
    expect(LolMatch::query()->find(LolEsportsResponses::MATCH_ID)->watch_ended_at)->not->toBeNull();
});

test('postponed match keeps polling and does not resend the reminder', function (): void {
    $this->travelTo('2026-10-15 17:45:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();
    $watcher = resolve(LolMatchWatcher::class);
    $watcher->tick();

    $this->travelTo('2026-10-15 18:05:00');
    LolEsportsResponses::fake(startTime: '2026-10-15T18:30:00Z');
    expect($watcher->tick())->toBe(300);

    $this->travelTo('2026-10-15 18:10:00');
    $watcher->tick();

    $this->travelTo('2026-10-15 18:31:00');
    LolEsportsResponses::fake(state: 'inProgress', startTime: '2026-10-15T18:30:00Z');
    $watcher->tick();

    Notification::assertSentToTimes($subscription->user, LolMatchReminderNotification::class, 1);
    Notification::assertSentToTimes($subscription->user, LolMatchStartedNotification::class, 1);
});

test('completed match ends the watch without notification', function (): void {
    $this->travelTo('2026-10-15 19:00:00');
    createWorldsMatch();
    $subscription = LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake(state: 'completed');

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull();
    Notification::assertNothingSentTo($subscription->user);
});

test('abandons a match never seen live 4 hours after its start', function (): void {
    $this->travelTo('2026-10-15 22:01:00');
    createWorldsMatch();
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    expect(resolve(LolMatchWatcher::class)->tick())->toBeNull();
    expect(LolMatch::query()->find(LolEsportsResponses::MATCH_ID)->watch_ended_at)->not->toBeNull();
});

test('team mode subscriber is only notified for followed teams', function (): void {
    $this->travelTo('2026-10-15 18:03:00');
    createWorldsMatch();
    $kcFan = LolLeagueSubscription::factory()->teams(['KC'])->create();
    $t1Fan = LolLeagueSubscription::factory()->teams(['T1'])->create();
    LolEsportsResponses::fake(liveIds: [LolEsportsResponses::MATCH_ID]);

    resolve(LolMatchWatcher::class)->tick();

    Notification::assertSentTo($kcFan->user, LolMatchStartedNotification::class);
    Notification::assertNotSentTo($t1Fan->user, LolMatchStartedNotification::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolMatchWatcherTest.php`
Expected: FAIL — `Class "App\Services\LolMatchWatcher" not found`.

- [ ] **Step 3: Implement**

`app/Services/LolMatchWatcher.php`:

```php
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
            $nextDueAt = $nextDueAt === null ? $dueAt : $nextDueAt->min($dueAt);
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
```

Note for the "postponed" test: at 18:05 the refreshed start is 18:30, so the user's reminder moment moves to 18:10 (future → next due, 300 s). At 18:10 the reminder is due again but `claim()` fails → no resend. ✔

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolMatchWatcherTest.php`
Expected: PASS (10 tests). Expected delays in the clamp test: 16:31 → reminder 17:40 is 69 min away → 900; 17:35 → reminder at 17:40 → 300; 17:57 → past live-check point → 60.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/LolMatchWatcher.php tests/Feature/Lol/LolMatchWatcherTest.php
git commit -m "feat(lol): add match watcher sending reminders and start notifications"
```

---

### Task 5: Jobs, command and schedule

**Files:**
- Create: `app/Jobs/LolTickJob.php`, `app/Jobs/SyncLolScheduleJob.php`, `app/Console/Commands/SyncLolSchedule.php`, `tests/Feature/Lol/LolJobsTest.php`
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: `LolMatchWatcher::tick()` (Task 4), `LolEsportsClient::schedule()` (Task 1), `LolMatch::upsertFromData()/pending()` (Task 2).
- Produces: `LolTickJob` (no args, `uniqueId(): string` = `'lol-tick'`, `$uniqueFor = 1200`), `SyncLolScheduleJob` (no args), command `lol:sync-schedule`.

- [ ] **Step 1: Write failing tests**

`tests/Feature/Lol/LolJobsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Jobs\LolTickJob;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Mocks\LolEsportsResponses;

test('sync fetches only subscribed leagues, stores matches and starts the tick', function (): void {
    $this->travelTo('2026-10-15 17:00:00');
    Queue::fake();
    LolLeagueSubscription::factory()->create(['league' => LolLeague::Worlds]);
    Http::fake(['*/getSchedule*' => Http::response(LolEsportsResponses::schedule([LolEsportsResponses::scheduleEvent()]))]);

    new SyncLolScheduleJob()->handle(resolve(App\Services\LolEsportsClient::class));

    expect(LolMatch::query()->count())->toBe(1);
    Http::assertSentCount(1);
    Queue::assertPushed(LolTickJob::class);
});

test('sync does not start the tick when no match is close', function (): void {
    $this->travelTo('2026-10-15 10:00:00');
    Queue::fake();
    LolLeagueSubscription::factory()->create();
    Http::fake(['*/getSchedule*' => Http::response(LolEsportsResponses::schedule([LolEsportsResponses::scheduleEvent()]))]);

    new SyncLolScheduleJob()->handle(resolve(App\Services\LolEsportsClient::class));

    Queue::assertNotPushed(LolTickJob::class);
});

test('sync without subscriptions calls nothing', function (): void {
    Queue::fake();
    Http::fake();

    $this->artisan('lol:sync-schedule')->assertSuccessful();

    Http::assertNothingSent();
});

test('tick re-dispatches itself with the watcher delay', function (): void {
    $this->travelTo('2026-10-15 17:35:00');
    Queue::fake();
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create(['id' => LolEsportsResponses::MATCH_ID]);
    LolLeagueSubscription::factory()->create();
    LolEsportsResponses::fake();

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertPushed(LolTickJob::class, fn (LolTickJob $job): bool => $job->delay === 300);
});

test('tick stops when there is nothing to watch', function (): void {
    Queue::fake();
    Http::fake();

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertNothingPushed();
});

test('tick survives api errors by retrying in 60 seconds', function (): void {
    $this->travelTo('2026-10-15 17:35:00');
    Queue::fake();
    LolMatch::factory()->startingAt(now()->parse('2026-10-15 18:00:00'))->create();
    Http::fake(['*' => Http::failedConnection()]);

    new LolTickJob()->handle(resolve(LolMatchWatcher::class));

    Queue::assertPushed(LolTickJob::class, fn (LolTickJob $job): bool => $job->delay === 60);
});

test('tick is unique until processing', function (): void {
    expect(new LolTickJob())->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and(new LolTickJob()->uniqueId())->toBe('lol-tick');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolJobsTest.php`
Expected: FAIL — job classes not found.

- [ ] **Step 3: Implement**

`app/Jobs/LolTickJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * Self re-dispatching watch loop. Unique *until processing* so the re-dispatch from handle() is accepted
 * while concurrent dispatches (hourly sync, new subscription) are absorbed when a tick is already pending.
 */
final class LolTickJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 1200;

    private const int RETRY_DELAY_SECONDS = 60;

    public function uniqueId(): string
    {
        return 'lol-tick';
    }

    public function handle(LolMatchWatcher $watcher): void
    {
        try {
            $delay = $watcher->tick();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('LoL esports API unavailable, retrying tick', ['error' => $exception->getMessage()]);
            $delay = self::RETRY_DELAY_SECONDS;
        }

        if ($delay !== null) {
            self::dispatch()->delay($delay);
        }
    }
}
```

`app/Jobs/SyncLolScheduleJob.php`:

```php
<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\LolLeague;
use App\Models\LolLeagueSubscription;
use App\Models\LolMatch;
use App\Services\LolEsportsClient;
use App\Services\LolMatchWatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SyncLolScheduleJob implements ShouldQueue
{
    use Queueable;

    public function handle(LolEsportsClient $client): void
    {
        $leagues = LolLeagueSubscription::query()->distinct()->pluck('league');

        foreach ($leagues as $league) {
            /** @var LolLeague $league */
            try {
                LolMatch::upsertFromData($client->schedule($league));
            } catch (Throwable $exception) {
                Log::warning('LoL schedule sync failed', ['league' => $league->value, 'error' => $exception->getMessage()]);
            }
        }

        $hasCloseMatch = LolMatch::query()
            ->pending()
            ->where('start_time', '<=', now()->addMinutes(LolMatchWatcher::WATCH_WINDOW_MINUTES))
            ->where('start_time', '>=', now()->subHours(LolMatchWatcher::ABANDON_AFTER_HOURS))
            ->exists();

        if ($hasCloseMatch) {
            LolTickJob::dispatch();
        }
    }
}
```

`app/Console/Commands/SyncLolSchedule.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncLolScheduleJob;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lol:sync-schedule')]
#[Description('Sync the LoL esports schedule of subscribed leagues and start the match watcher when a match is close.')]
final class SyncLolSchedule extends Command
{
    public function handle(): void
    {
        SyncLolScheduleJob::dispatchSync();

        $this->info('LoL schedule synced.');
    }
}
```

`routes/console.php` — append:

```php
Schedule::command('lol:sync-schedule')->hourly();
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolJobsTest.php`
Expected: PASS. Check `php artisan schedule:list` shows `lol:sync-schedule` hourly.

- [ ] **Step 5: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Jobs/LolTickJob.php app/Jobs/SyncLolScheduleJob.php app/Console/Commands/SyncLolSchedule.php routes/console.php tests/Feature/Lol/LolJobsTest.php
git commit -m "feat(lol): add hourly schedule sync and self re-dispatching tick job"
```

---

### Task 6: Minimal `/lol` page with per-league toggle

**Files:**
- Create: `app/Http/Controllers/LolController.php`, `resources/js/pages/Lol.vue`, `tests/Feature/Lol/LolControllerTest.php`
- Modify: `routes/web.php`, `resources/js/components/AppSidebar.vue`

**Interfaces:**
- Consumes: `LolLeague`, `LolLeagueSubscription`, `SyncLolScheduleJob` (Tasks 1, 2, 5).
- Produces: routes `GET /lol` name `lol`, `POST /lol/leagues/{league}` name `lol.leagues.toggle` (enum-bound `LolLeague $league`); Inertia page `Lol` with prop `leagues: list<{slug: string, label: string, image: string, subscribed: bool}>`.

- [ ] **Step 1: Write failing tests**

`tests/Feature/Lol/LolControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\LolLeague;
use App\Jobs\SyncLolScheduleJob;
use App\Models\LolLeagueSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('guests are redirected to login', function (): void {
    $this->get(route('lol'))->assertRedirect(route('login'));
});

test('page lists leagues with subscription state', function (): void {
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->create(['league' => LolLeague::Worlds]);

    $this->actingAs($user)
        ->get(route('lol'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Lol')
            ->has('leagues', 5)
            ->where('leagues.0', [
                'slug' => 'worlds',
                'label' => 'Worlds',
                'image' => LolLeague::Worlds->image(),
                'subscribed' => true,
            ])
            ->where('leagues.1.subscribed', false));
});

test('toggle subscribes and triggers a schedule sync', function (): void {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('lol.leagues.toggle', 'worlds'))->assertRedirect();

    expect($user->lolLeagueSubscriptions()->where('league', 'worlds')->exists())->toBeTrue();
    Queue::assertPushed(SyncLolScheduleJob::class);
});

test('toggle unsubscribes when already subscribed', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    LolLeagueSubscription::factory()->for($user)->create(['league' => LolLeague::Worlds]);

    $this->actingAs($user)->post(route('lol.leagues.toggle', 'worlds'))->assertRedirect();

    expect($user->lolLeagueSubscriptions()->exists())->toBeFalse();
    Queue::assertNotPushed(SyncLolScheduleJob::class);
});

test('unknown league is not found', function (): void {
    $this->actingAs(User::factory()->create())
        ->post('/lol/leagues/lpl')
        ->assertNotFound();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Lol/LolControllerTest.php`
Expected: FAIL — route `lol` not defined.

- [ ] **Step 3: Implement backend**

`app/Http/Controllers/LolController.php`:

```php
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
```

`routes/web.php` — add `use App\Http\Controllers\LolController;` and after the twitch group:

```php
Route::prefix('lol')->middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/', [LolController::class, 'index'])->name('lol');
    Route::post('leagues/{league}', [LolController::class, 'toggleLeague'])->name('lol.leagues.toggle');
});
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --compact tests/Feature/Lol/LolControllerTest.php`
Expected: PASS. (Inertia page-existence check may require the Vue file — do Step 5 first if it fails on a missing component.)

- [ ] **Step 5: Implement frontend**

Run `php artisan wayfinder:generate --with-form --no-interaction` (or let the Vite plugin regenerate), then create `resources/js/pages/Lol.vue`:

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Bell, BellOff } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { toggle } from '@/routes/lol/leagues';
import { type BreadcrumbItem } from '@/types';

type League = {
    slug: string;
    label: string;
    image: string;
    subscribed: boolean;
};

defineProps<{ leagues: League[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'League of Legends', href: '/lol' },
];

const toggleLeague = (league: League) => {
    router.post(toggle.url(league.slug), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="League of Legends" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <p class="text-sm text-muted-foreground">
                Follow a league to get a reminder before each match and a
                notification when it starts.
            </p>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="league in leagues"
                    :key="league.slug"
                    class="flex items-center gap-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border"
                >
                    <img
                        :src="league.image"
                        :alt="league.label"
                        class="size-12 rounded-md bg-neutral-900 object-contain p-1"
                    />
                    <span class="flex-1 font-medium">{{ league.label }}</span>
                    <Button
                        :variant="league.subscribed ? 'default' : 'outline'"
                        size="sm"
                        @click="toggleLeague(league)"
                    >
                        <Bell v-if="league.subscribed" class="size-4" />
                        <BellOff v-else class="size-4" />
                        {{ league.subscribed ? 'Following' : 'Follow' }}
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
```

`resources/js/components/AppSidebar.vue` — import `Trophy` from `lucide-vue-next` and add to `mainNavItems`:

```ts
    {
        title: 'League of Legends',
        href: '/lol',
        icon: Trophy,
    },
```

- [ ] **Step 6: Verify frontend**

Run: `bun run build && bun run test:lint && bun run test:types`
Expected: build OK, no lint/type errors. Then rerun `php artisan test --compact tests/Feature/Lol/LolControllerTest.php` → PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/LolController.php routes/web.php resources/js/pages/Lol.vue resources/js/components/AppSidebar.vue tests/Feature/Lol/LolControllerTest.php
git commit -m "feat(lol): add league follow page"
```

---

### Task 7: Full verification and local run for tonight

**Files:** none (verification only).

- [ ] **Step 1: Full suite**

Run: `composer test`
Expected: type coverage 100 %, all tests pass, pint/rector/oxlint clean, PHPStan 0 errors. Fix any new PHPStan errors in the new code (do NOT add them to the baseline).

- [ ] **Step 2: Local end-to-end**

```bash
php artisan migrate
# subscribe to Worlds on http://localhost/lol (push permission already granted via /twitch page)
php artisan lol:sync-schedule
php artisan queue:work --verbose
```

Expected: `lol_matches` contains `115660588583171786` (C9 vs KC, 18:00Z). From 16:30Z the tick chain runs; reminder push at 17:40Z, start push shortly after `getLive` reports it `inProgress`.

- [ ] **Step 3: Commit any fixes**

```bash
git add -A -- app tests resources routes
git commit -m "fix(lol): address full-suite findings"
```

---

### Task 8: Production (requires explicit user approval before each step)

- [ ] **Step 1:** `composer require aws/aws-sdk-php` → run `composer test` → commit `chore: add aws sdk for Laravel Cloud managed queues`.
- [ ] **Step 2:** Ask the user before running `cloud queue:create` (Flex, standard, default) on `env-a286c37a-6803-448a-bb3a-75664f0b3eb2`. Warn: `SendBatchedChannelUpdateNotification` will start being processed in prod.
- [ ] **Step 3:** Ask the user before pushing / deploying. After deploy: check `schedule:list` on Cloud includes `lol:sync-schedule`, watch the Queues dashboard.

---

## Phase 2 (separate plan, later)

Team chips + Teams mode UI, co-streamer field, Settings › League of Legends (reminder delay), upcoming matches list with LIVE badge and 🔔 indicator.
