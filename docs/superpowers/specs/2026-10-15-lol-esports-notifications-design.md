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
- `getLive?hl=en-GB` : matchs réellement en cours (`events[].id` = id du match,
  `state: inProgress`).
- `getEventDetails` n'expose **ni `startTime` ni `state`** : non utilisé.
- `getSchedule` est paginé (~80 events, `pages.newer`) et partage cette page
  entre ligues si plusieurs `leagueId` : un appel par ligue, suivre
  `pageToken={newer}` si la fenêtre voulue n'est pas couverte.
- Images en `http://static.lolesports.com/...` : réécrites en `https://`.
- Ids de match à 18 chiffres : toujours des strings (PHP, DB, JS).

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

Décision : une managed queue Flex standard + **un seul job "tick" global** qui
se re-dispatch tant qu'un match est proche. Polling uniquement autour des
matchs, app endormie le reste du temps. L'app se réveille déjà toutes les
heures (`app:report-event-sub-heartbeat` est `hourly()`), la sync horaire ne
coûte donc rien de plus.

## Architecture

```
Scheduler hourly (réveil déjà existant)
  └─ lol:sync-schedule → dispatch SyncLolScheduleJob

SyncLolScheduleJob
  ├─ getSchedule (1 appel/ligue) pour chaque ligue ayant ≥1 abonnement
  ├─ upsert lol_matches (events type=match uniquement)
  └─ si un match unstarted|inProgress a start_time ≤ now + 90 min → dispatch LolTickJob

LolTickJob  (ShouldBeUniqueUntilProcessing, uniqueId 'lol-tick', uniqueFor 20 min)
  ├─ matchs actifs = lol_matches non notifiés "started", state ≠ completed,
  │  start_time ∈ [now − 4 h, now + 90 min] ; aucun → fin de chaîne
  ├─ refresh : getSchedule des ligues concernées → start_time / state / block / teams
  │  (gère retards et reports ; refresh fait avant la règle d'abandon)
  ├─ getLive (1 appel) → ids inProgress
  ├─ par match :
  │  ├─ completed → marqué traité, pas de notif rattrapée
  │  ├─ inProgress (getLive ou schedule) → LolMatchStartedNotification aux abonnés concernés
  │  └─ unstarted → rappels : abonnés concernés avec lol_reminder_minutes non null,
  │     start_time − offset ≤ now et start_time − now ≥ 1 min → LolMatchReminderNotification
  │     (minutes affichées = ceil(start_time − now), pas l'offset)
  └─ re-dispatch LolTickJob avec delay = clamp(prochain moment dû − now, 60 s, 15 min)
     (prochain moment dû = plus proche start_time − offset en attente, ou start_time − 2 min ;
      dès start_time − 2 min : 60 s)
```

Dedup / at-least-once :
- Avant chaque push : `insertOrIgnore` dans `lol_match_notifications` ;
  on n'envoie que si la ligne a été insérée.
- Le lock `ShouldBeUniqueUntilProcessing` est relâché au début du traitement,
  donc le re-dispatch depuis `handle()` passe ; un dispatch concurrent (sync,
  abonnement) est absorbé tant qu'un tick est en attente. **Ne pas** utiliser
  `ShouldBeUnique` (le re-dispatch serait ignoré et la chaîne mourrait).
- Les locks utilisent le cache store : `CACHE_STORE=database` (défaut de
  `config/cache.php`, table `cache_locks`) partagé entre app et compute Flex.
  Un store `file`/`array` casserait l'unicité.
- Erreur HTTP / réseau : pas de `release()` (consomme les tentatives) ; on
  catch, log, et re-dispatch un nouveau tick à 60 s.

Déclencheur supplémentaire : à l'enregistrement d'un abonnement (l'app est
réveillée par la requête), dispatch de `SyncLolScheduleJob`.

"Abonné concerné" pour un match : utilisateur avec un `lol_league_subscriptions`
sur la ligue du match, et soit `mode = all`, soit `mode = teams` avec au moins
un code d'équipe du match dans `team_codes`. Les équipes `TBD` ne matchent jamais.

Erreur dans la sync → log, la prochaine sync horaire rattrape.

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
  id (string PK, id lolesports ; $incrementing=false, $keyType='string'), league (LolLeague), start_time (datetime UTC)
  state (string), block_name (string nullable), best_of (unsigned tinyint nullable)
  teams (json : [{name, code, image}]), watch_ended_at (nullable : début notifié,
  match terminé ou abandonné ; jamais écrasé par la sync), timestamps

lol_match_notifications
  id, lol_match_id (fk string cascade), user_id (fk cascade), type (reminder|started)
  sent_at, unique(lol_match_id, user_id, type)
```

## Notifications

`LolMatchReminderNotification` et `LolMatchStartedNotification`, `WebPushChannel`,
textes traduits dans `lang/` comme Twitch.

- Textes dans `lang/en/lol.php` (seule locale existante, comme `twitch.php`).
- Rappel : titre « ⏰ KC vs C9 in 20 min » (minutes réelles restantes), corps « Worlds · Play-Ins · BO5 · 20:00 » (heure Europe/Brussels).
- Début : titre « 🔴 KC vs C9 has started », corps « Worlds · Play-Ins · BO5 ».
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
- `App\Services\LolEsportsClient` (readonly, `Http` facade, méthodes `schedule`, `live`)
- `App\Models\LolLeagueSubscription`, `App\Models\LolMatch`, `App\Models\LolMatchNotification` (+ factories)
- `App\Jobs\SyncLolScheduleJob`, `App\Jobs\LolTickJob`
- `App\Console\Commands\SyncLolSchedule` (`lol:sync-schedule`)
- `App\Notifications\LolMatchReminderNotification`, `LolMatchStartedNotification`
- `App\Http\Controllers\LolController` (index, upsert/delete abonnement)
- `App\Http\Controllers\Settings\LolSettingsController` (édition délai rappel)

## Tests

Pest, `Http::fake` (réponses API fixtures), `Notification::fake`, `Queue::fake`,
`freezeTime` / `travelTo` :

- sync : upsert des matchs, dispatch des watchers, ligues sans abonné non fetchées.
- tick : rappel envoyé au bon moment, une seule fois ; respect de l'offset
  utilisateur et de `null` ; notif de début sur `inProgress` puis plus de
  re-dispatch ; delay ≤ 15 min ; abandon après 4 h ; `completed` stoppe ;
  rappel tardif (offset > temps restant) affiche les minutes réelles ;
  dedup (2 ticks → 1 push) ; erreur HTTP → re-dispatch 60 s.
- filtre teams (match / pas match / TBD).
- notification : URL costream vs lolesports, icône.
- controllers : abonnement (validation, unique), réglage rappel.

## Déploiement

1. `composer require aws/aws-sdk-php` (requis par les managed queues, absent de `composer.json`).
2. Créer une managed queue Flex standard sur l'env production (`cloud queue:create`), définie par défaut → Cloud injecte `QUEUE_CONNECTION=cloud`.
   Effet de bord : `SendBatchedChannelUpdateNotification` sera réellement
   traité en prod pour la première fois → à surveiller.
3. Déployer (migrations via deploy command existante ; le scheduler est déjà actif, le nouveau `dailyAt` est capturé au déploiement).

Local : `QUEUE_CONNECTION=database` + `php artisan queue:work` + `php artisan lol:sync-schedule`.

## Phases

- **Phase 1 (ce soir, avant 18:00Z)** : migrations complètes, enums, client,
  sync + tick, filtre all/teams côté backend, notifications, commande, page
  `/lol` minimale (toggle par ligue, mode Tous), délai de rappel par défaut 20.
- **Phase 2** : chips d'équipes + mode Équipes dans l'UI, co-streamer,
  Settings › League of Legends, liste des matchs à venir.

## Hors scope

Résultats / scores, autres ligues, statut live du co-streamer, Kick.
