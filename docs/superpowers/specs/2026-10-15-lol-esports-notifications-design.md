# Notifications League of Legends Esports

## Contexte

obsvr notifie aujourd'hui les débuts de stream Twitch via WebPush
(`WebPushChannel` sur `User`, une classe `Notification` par événement), en
s'appuyant sur des webhooks EventSub : l'app ne travaille que quand Twitch
l'appelle, ce qui colle au scale-to-zero de Laravel Cloud.

Objectif : être notifié des matchs LoL esport (Worlds en cours, puis MSI,
First Stand, LCK, LEC) en réutilisant ce système : un rappel avant le match et
une notification au vrai début du match. Premier test réel : Worlds Play-Ins,
C9 vs KC, 2026-10-15 18:00Z (20:00 Bruxelles).

## Source de données

API non officielle utilisée par lolesports.com, clé publique (pas de compte) :

- Base : `https://esports-api.lolesports.com/persisted/gw`
- Header : `x-api-key: 0TvQnueqKa5mxJntVWt0w4LpLfEkrV1Ta8rQBb9Z` (dans `config/services.php`, surchargeable par env)
- `getSchedule?hl=en-GB&leagueId={id}` : events avec `startTime`, `state`
  (`unstarted` | `inProgress` | `completed`), `blockName`, `match.id`,
  `match.strategy.count` (BO), `match.teams[]` (`name`, `code`, `image`, `record`).
- `getEventDetails?hl=en-GB&id={matchId}` : rafraîchir un match.
- `getLive?hl=en-GB` : matchs réellement en cours.

Pas de webhook disponible : il faut poller, intelligemment.

Ligues (enum en dur `LolLeague`) :

| slug | leagueId |
| - | - |
| worlds | 98767975604431411 |
| msi | 98767991325878492 |
| first_stand | 113464388705111224 |
| lck | 98767991310872058 |
| lec | 98767991302996019 |

## Contraintes d'infra (Laravel Cloud)

Constaté en prod (env `production` de l'app `obsvr`) : App cluster flex-512mb,
Octane, hibernation activée, scheduler activé, **aucune managed queue ni
background process** (le job `SendBatchedChannelUpdateNotification` n'est donc
probablement jamais traité en prod).

- Le scheduler réveille l'app aux horaires de `schedule:list`, capturés
  **au déploiement** : impossible de planifier dynamiquement à l'heure d'un match.
  Chaque réveil garde l'app éveillée le temps du sleep timeout.
- Les managed queues **Flex** tournent sur leur propre compute, se réveillent
  en < 1 s à l'arrivée d'un job, et ne réveillent pas l'app. Délai par job
  max **15 min** (queue standard, pas FIFO). Durée max d'un job : 90 s.

Décision : une managed queue Flex standard + un job "watcher" par match qui se
re-dispatch. Polling uniquement autour des matchs, app endormie le reste du temps.

## Architecture

```
Scheduler (dailyAt 07:00 Europe/Brussels → 1 réveil Cloud/jour)
  └─ lol:sync-schedule → dispatch SyncLolScheduleJob

SyncLolScheduleJob
  ├─ getSchedule pour chaque ligue ayant ≥1 abonnement
  ├─ upsert lol_matches (events type=match uniquement)
  └─ pour chaque match unstarted|inProgress démarrant dans les 36 h
     et sans watcher actif (lock cache par match) → WatchLolMatchJob

WatchLolMatchJob(matchId)
  ├─ getEventDetails → met à jour start_time / state / teams (gère retards, reports)
  ├─ rappels : pour chaque abonné concerné avec lol_reminder_minutes non null,
  │  si start_time − offset ≤ now < start_time → LolMatchReminderNotification (dedup)
  ├─ si now ≥ start_time − 2 min : getLive (ou state de getEventDetails)
  │  inProgress → LolMatchStartedNotification à tous les abonnés concernés (dedup) → fin
  ├─ completed → fin (pas de notif de début rattrapée)
  ├─ sinon re-dispatch avec delay = clamp(prochain moment dû − now, 60 s, 15 min)
  │  (prochain moment dû = plus proche des start_time − offset en attente, ou start_time − 2 min)
  │  à partir de start_time − 2 min : delay = 60 s
  └─ abandon si now > start_time + 4 h
```

Déclencheurs de sync supplémentaires : à l'enregistrement d'un abonnement
(l'app est réveillée par la requête), dispatch de `SyncLolScheduleJob` pour
que les nouveaux abonnés soient couverts sans attendre le lendemain.

"Abonné concerné" pour un match : utilisateur avec un `lol_league_subscriptions`
sur la ligue du match, et soit `mode = all`, soit `mode = teams` avec au moins
un code d'équipe du match dans `team_codes`. Les équipes `TBD` ne matchent jamais.

Erreurs :
- Erreur HTTP / réseau dans le watcher → `release(60)`, il continue.
- Erreur dans la sync → log, la prochaine sync rattrape.
- Jobs idempotents (at-least-once delivery) : dedup via contrainte unique.

## Modèle de données

```
lol_league_subscriptions
  id, user_id (fk cascade), league (LolLeague)
  mode (LolSubscriptionMode: all|teams, défaut all)
  team_codes (json nullable), costream_channel (string nullable)
  timestamps, unique(user_id, league)

users
  + lol_reminder_minutes (unsigned smallint nullable, défaut 20 ; null = pas de rappel)
    valeurs proposées : off, 5, 10, 15, 20, 30, 60

lol_matches
  id (string PK, id lolesports), league (LolLeague), start_time (datetime UTC)
  state (string), block_name (string nullable), best_of (unsigned tinyint nullable)
  teams (json : [{name, code, image}]), timestamps

lol_match_notifications
  id, lol_match_id (fk), user_id (fk cascade), type (reminder|started)
  sent_at, unique(lol_match_id, user_id, type)
```

## Notifications

`LolMatchReminderNotification` et `LolMatchStartedNotification`, `WebPushChannel`,
textes traduits dans `lang/` comme Twitch.

- Rappel : titre « ⏰ KC vs C9 dans 20 min », corps « Worlds · Play-Ins · BO5 · 20:00 » (heure Europe/Brussels).
- Début : titre « 🔴 KC vs C9 a commencé », corps « Worlds · Play-Ins · BO5 ».
- Icône : logo d'une équipe suivie si mode teams, sinon logo de la ligue.
- Lien / action : `https://twitch.tv/{costream_channel}` si défini
  (« Regarder chez {channel} »), sinon `https://lolesports.com/live/{league}`.

## UI

- Page `/lol` (Inertia `Lol.vue`, lien dans la nav à côté de Twitch) :
  - une carte par ligue : toggle abonnement, mode Tous / Équipes, chips
    d'équipes (multi-select, logo + code, issues des `lol_matches` de la ligue),
    champ co-streamer.
  - liste des matchs à venir (7 jours) des ligues abonnées : logos, codes,
    heure Bruxelles, BO, block, badge LIVE si `inProgress`, indicateur 🔔 si
    l'utilisateur sera notifié selon son filtre.
- Settings › League of Legends : délai de rappel (select).
- Routes Wayfinder, formulaires Inertia comme le reste de l'app.

## Composants

- `App\Enums\LolLeague`, `App\Enums\LolSubscriptionMode`
- `App\Services\LolEsportsClient` (readonly, `Http` facade, méthodes `schedule`, `eventDetails`, `live`)
- `App\Models\LolLeagueSubscription`, `App\Models\LolMatch`, `App\Models\LolMatchNotification` (+ factories)
- `App\Jobs\SyncLolScheduleJob`, `App\Jobs\WatchLolMatchJob`
- `App\Console\Commands\SyncLolSchedule` (`lol:sync-schedule`)
- `App\Notifications\LolMatchReminderNotification`, `LolMatchStartedNotification`
- `App\Http\Controllers\LolController` (index, upsert/delete abonnement)
- `App\Http\Controllers\Settings\LolSettingsController` (édition délai rappel)

## Tests

Pest, `Http::fake` (réponses API fixtures), `Notification::fake`, `Queue::fake`,
`freezeTime` / `travelTo` :

- sync : upsert des matchs, dispatch des watchers, ligues sans abonné non fetchées.
- watcher : rappel envoyé au bon moment, une seule fois ; respect de l'offset
  utilisateur et de `null` ; notif de début sur `inProgress` puis plus de
  re-dispatch ; delay ≤ 15 min ; abandon après 4 h ; `completed` stoppe.
- filtre teams (match / pas match / TBD).
- notification : URL costream vs lolesports, icône.
- controllers : abonnement (validation, unique), réglage rappel.

## Déploiement

1. `composer require aws/aws-sdk-php` (requis par les managed queues).
2. Créer une managed queue Flex standard sur l'env production (`cloud queue:create`), définie par défaut → Cloud injecte `QUEUE_CONNECTION=cloud`.
3. Déployer (migrations via deploy command existante ; le scheduler est déjà actif, le nouveau `dailyAt` est capturé au déploiement).

Local : `QUEUE_CONNECTION=database` + `php artisan queue:work` + `php artisan lol:sync-schedule`.

## Hors scope

Résultats / scores, autres ligues, statut live du co-streamer, Kick.
