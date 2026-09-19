# Kick OAuth + Abstraction Multi-Plateforme (Twitch/Kick)

## Contexte

L'application ne supporte aujourd'hui que Twitch : OAuth (Socialite), tokens
stockés directement sur `users`, un `TwitchApiClient` + `TwitchTokenManagerService`
monolithiques, des notifications WebPush nommées `Twitch*` avec des liens en dur
`twitch.tv/...`, et une réception de webhooks EventSub via `TwitchEventSubController`.

Objectif : ajouter Kick (`socialiteproviders/kick` déjà installé côté composer)
comme deuxième plateforme, à la fois comme méthode de login et comme compte
liable à un utilisateur déjà connecté (compte "fusionné" Twitch+Kick), avec un
bouton "Se connecter avec Kick" sur la page de login (sous le bouton Twitch
existant). Le système de notifications de live doit fonctionner
indépendamment de la plateforme dont provient l'événement.

Le code d'implémentation sera écrit par l'utilisateur ; ce document sert de
référence architecturale.

## Décisions validées

1. **Kick a des webhooks natifs** (équivalent EventSub) — pas de polling à prévoir.
2. **Abstraction complète en 3 responsabilités** : token management, API REST,
   vérification/parsing des webhooks. Chacune a son propre contrat (interface
   segregation), pas une seule grosse interface par provider.
3. **Multi-provider par user** : un même compte `users` peut avoir un
   `oauth_account` Twitch ET un `oauth_account` Kick simultanément (compte
   fusionné). Nécessite de sortir les colonnes OAuth de `users` vers une table
   dédiée.
4. **Kick = login ET liaison** : un visiteur non connecté peut se logger
   directement via Kick (comme Twitch aujourd'hui) ; un utilisateur déjà
   connecté (peu importe le provider d'origine) peut lier un second provider à
   son compte existant.
5. **`favourite_streamers` et `subscriptions`** gagnent une colonne `platform`
   (enum `twitch`/`kick`) pour distinguer les entités par plateforme.
6. **3 Managers Laravel indépendants** (`Illuminate\Support\Manager`), un par
   interface, plutôt qu'un manager composite unique.
7. **Login/liaison géré dans le même controller/route**, sans middleware
   `guest`, en distinguant via `Auth::check()`.
8. **Notifications généralisées** : renommées (sans préfixe `Twitch`), DTO
   générique portant `platform: StreamingPlatform`, URL du stream construite
   via une méthode sur l'enum plutôt qu'en dur.
9. **Webhooks : controllers séparés par plateforme**, logique métier partagée
   via les interfaces/managers communs (pas de route générique `/webhooks/{platform}`,
   car signatures/middlewares de sécurité diffèrent par provider).
10. **`TwitchEvent` renommé `PlatformEvent`** + colonne `platform`, table
    unique pour l'historique webhook tous providers confondus.
11. **Migration de données sans perte** : approche en 2 étapes (voir plan de
    migration ci-dessous), jamais de drop de colonnes tant que le backfill
    n'est pas confirmé.
12. **Bouton Kick sur la page de login** (`resources/js/pages/auth/Login.vue`),
    sous le bouton Twitch existant, même mécanique (`redirect('kick').url`).
    Couleur de marque officielle Kick `#53FC18` (texte noir pour le contraste),
    logo Kick en SVG inline, même pattern que `ConnectWithTwitch.vue`.
13. **Fix du wrapper CSS bugué** : `ConnectWithTwitch.vue` (et son usage dans
    `Welcome.vue`) enveloppe le bouton dans une `<div>` avec des classes de
    "card" qui n'ont rien à faire là (`min-h-[100vh]`, `border`, `rounded-xl`).
    Ce wrapper est retiré à l'occasion de l'ajout du bouton Kick, sur les deux
    composants, pour ne garder que le `<a>` stylisé en bouton.

## Modèle de données

### Nouvelle table `oauth_accounts`

```
id (bigint, PK)
user_id (FK -> users.id, cascade on delete)
provider (enum: twitch, kick)
provider_id (string)             # ID unique côté plateforme
access_token (string, encrypted)
refresh_token (string, encrypted)
expires_at (timestamp, nullable)
scopes (json)
created_at / updated_at

unique(provider, provider_id)    # un compte plateforme ne peut être lié qu'à un seul user
unique(user_id, provider)        # un user ne peut lier qu'un compte par plateforme
```

### `users`

Colonnes retirées (après migration des données, voir plan) :
`auth_provider`, `auth_provider_id`, `auth_provider_access_token`,
`auth_provider_refresh_token`, `auth_provider_expires_at`.

`avatar_url` reste sur `users` (mis à jour lors du login/liaison, peu importe
le provider utilisé pour le dernier login).

### `favourite_streamers`

Ajout colonne `platform` (enum: twitch, kick), non nullable, avec valeur par
défaut `twitch` pour les lignes existantes lors de la migration.

### `subscriptions`

Ajout colonne `platform` (enum: twitch, kick), même traitement que ci-dessus.

### `twitch_events` → `platform_events`

Renommage de table + ajout colonne `platform` (défaut `twitch` pour les lignes
existantes). Renommer aussi le modèle `TwitchEvent` → `PlatformEvent`.

## Contrats (interfaces)

```php
namespace App\Contracts;

interface PlatformTokenManager
{
    public function ensureFreshUserAccessToken(OauthAccount $account): OauthAccount;

    public function ensureFreshAppAccessToken(): string;

    public function clearTokens(OauthAccount $account): void;
}

interface PlatformApiClient
{
    public function getStatusOfFollowedStreamers(OauthAccount $account): StreamCollectionData;

    public function getFollowedChannels(OauthAccount $account): ChannelCollectionData;

    public function getStatusOfStreamer(string $channelId): ?StreamData;

    // ... reprendre les autres méthodes utiles de TwitchApiClient (schedule,
    // subscriptions EventSub côté API sortante), signatures platform-agnostic.
}

interface PlatformWebhookVerifier
{
    public function verifySignature(Request $request): bool;

    public function parseEvent(Request $request): PlatformWebhookEventData;
}
```

`StreamCollectionData`, `ChannelCollectionData`, `StreamData`,
`PlatformWebhookEventData` sont des DTOs Spatie Data **génériques**
(remplacent `TwitchStreamData`, `TwitchStreamPaginatedResponse`, etc., ou les
enveloppent) portant un champ `platform: StreamingPlatform`.

## Enum et Managers

```php
enum StreamingPlatform: string
{
    case Twitch = 'twitch';
    case Kick = 'kick';

    public function streamUrl(string $streamerLogin): string
    {
        return match ($this) {
            self::Twitch => "https://twitch.tv/{$streamerLogin}",
            self::Kick => "https://kick.com/{$streamerLogin}",
        };
    }
}
```

Trois managers (pattern `Illuminate\Support\Manager`, même mécanique que
`Illuminate\Cache\CacheManager`) :

- `PlatformTokenManagerFactory::driver(StreamingPlatform $platform): PlatformTokenManager`
- `PlatformApiClientFactory::driver(StreamingPlatform $platform): PlatformApiClient`
- `PlatformWebhookVerifierFactory::driver(StreamingPlatform $platform): PlatformWebhookVerifier`

Chacun résout `createTwitchDriver()` / `createKickDriver()` en interne. Le
code consommateur (jobs, controllers, MCP tools) injecte la factory dont il a
besoin et appelle `->driver($platform)`, sans connaître l'implémentation
concrète.

Implémentations concrètes attendues :
`App\Services\Twitch\{TwitchTokenManager,TwitchApiClient,TwitchWebhookVerifier}`
`App\Services\Kick\{KickTokenManager,KickApiClient,KickWebhookVerifier}`

## Flow d'authentification

Route (retrait du middleware `guest`, extension du `where`) :

```php
Route::prefix('auth/{provider}')->where(['provider' => 'twitch|kick'])->group(function (): void {
    Route::get('redirect', [SocialiteController::class, 'redirect'])->name('socialite.redirect');
    Route::get('callback', [SocialiteController::class, 'handleCallback'])->name('socialite.callback');
});
```

Logique dans `SocialiteController::handleCallback()` :

1. Récupérer le `SocialiteUser` comme aujourd'hui.
2. Chercher un `OauthAccount` existant par `(provider, provider_id)`.
   - **Trouvé** : mettre à jour ses tokens, logger l'utilisateur propriétaire
     (`$oauthAccount->user`). Si `Auth::check()` et que l'utilisateur connecté
     diffère de `$oauthAccount->user`, erreur (ce compte plateforme est déjà
     lié à un autre user).
   - **Non trouvé, `Auth::check()` est true** : mode liaison — créer un
     `OauthAccount` rattaché à `Auth::user()`, ne pas relogger.
   - **Non trouvé, invité** : mode login — créer un `User` (comme aujourd'hui,
     minus les colonnes retirées) + un `OauthAccount` rattaché, logger.
3. Mettre à jour `avatar_url` sur `users` si fourni par le provider utilisé.

## Notifications génériques

Renommer :
- `TwitchStreamerStreamStartedNotification` → `StreamerStreamStartedNotification`
- `TwitchChannelUpdatedNotification` → `ChannelUpdatedNotification`
- `TwitchChannelUpdateBatchedNotification` → `ChannelUpdateBatchedNotification`

Chaque notification reçoit un DTO générique (ex: `StreamerNotificationData`)
contenant a minima `platform: StreamingPlatform`, `streamerLogin: string`,
`streamerDisplayName: string`, et les champs spécifiques déjà existants
(titre, jeu, etc.). Le lien de la notif est construit via
`$data->platform->streamUrl($data->streamerLogin)` au lieu du `twitch.tv`
codé en dur.

`TwitchChannelUpdateMessageData` suit la même généralisation.

## Webhooks entrants

- `TwitchEventSubController` (existant, route `POST twitch/eventsub`) :
  refactor interne pour utiliser `PlatformWebhookVerifierFactory::driver(StreamingPlatform::Twitch)`
  au lieu de la logique de vérification inline. Comportement HTTP inchangé.
- **Nouveau** `KickEventSubController` (route `POST kick/eventsub`), même
  structure, utilise le driver Kick. Nécessite un nouveau middleware
  `VerifyKickEventSubSignatureMiddleware` (algorithme de signature propre à
  Kick, à vérifier dans leur doc API au moment de l'implémentation).
- Les deux controllers partagent :
  - la persistance dans `PlatformEvent` (avec `platform` renseigné),
  - le dispatch de `SendBatchedChannelUpdateNotification` (le job devient
    lui-même agnostique : il résout la plateforme depuis le `FavouriteStreamer`/`Subscriptions`
    concerné plutôt que de supposer Twitch),
  - l'envoi des notifications génériques ci-dessus.

## Plan de migration (sans perte de données)

Trois migrations distinctes, exécutées dans cet ordre, avec vérification
manuelle entre chaque étape en prod :

1. **Migration additive** : créer `oauth_accounts`, ajouter `platform` sur
   `favourite_streamers`/`subscriptions` (défaut `twitch`), renommer
   `twitch_events` → `platform_events` + ajouter `platform` (défaut `twitch`).
   Aucune colonne existante n'est touchée.
2. **Backfill** : commande Artisan (`app:backfill-oauth-accounts`) qui, pour
   chaque `User` ayant `auth_provider` non null, crée l'`OauthAccount`
   correspondant à partir des colonnes existantes. Idempotente (skip si
   l'`OauthAccount` existe déjà). Exécutée manuellement en prod, résultat
   vérifié (compter `users` avec `auth_provider` non null == compter
   `oauth_accounts`) avant de passer à l'étape 3.
3. **Migration destructive** (déployée seulement après confirmation manuelle
   du backfill) : drop des colonnes `auth_provider*` sur `users`.

Cette séparation permet un rollback simple entre les étapes 1/2 sans perte, et
l'étape 3 n'est jouée qu'une fois la donnée validée.

## Page de login (front)

- `resources/js/pages/auth/Login.vue` : ajouter `<ConnectWithKick class="w-full" :redirect="redirect('kick').url" fill />`
  sous le `<ConnectWithTwitch ... />` existant (ligne ~101).
- Nouveau composant `resources/js/components/ConnectWithKick.vue`, calqué sur
  `ConnectWithTwitch.vue` mais :
  - sans le `<div>` wrapper "card" bugué (fix appliqué aussi à `ConnectWithTwitch.vue`
    et son usage dans `Welcome.vue`),
  - couleur de fond `#53FC18` (vert Kick officiel), texte noir, hover légèrement
    plus sombre,
  - logo Kick en SVG inline (à récupérer depuis les brand assets officiels Kick),
  - clé de traduction `app.cta.kick_connect` (à ajouter à côté de
    `app.cta.twitch_connect`).
- Backend, en plus des changements déjà listés dans "Flow d'authentification" :
  - `routes/web.php` : `->where(['provider' => 'twitch'])` → `->where(['provider' => 'twitch|kick'])`.
  - `SocialiteController::validateOrThrow` : règle `'provider' => ['required', 'string', 'in:twitch']` → `in:twitch,kick`.
  - `AuthProvider` enum : ajouter `case Kick = 'kick';` (utilisé le temps de la
    transition ; à terme la source de vérité du provider est `oauth_accounts.provider`).
  - `config/services.php` : bloc `kick` (client_id, client_secret, redirect,
    éventuels `auth_url`/`base_url` selon ce qu'expose `socialiteproviders/kick`).
  - `AppServiceProvider` : enregistrer le listener `SocialiteWasCalled` pour
    Kick (`KickExtendSocialite::handle`, fourni par le package
    `socialiteproviders/kick`), à côté de celui de Twitch.

## Hors scope

- Gestion de la révocation/déconnexion d'un provider lié (peut être ajoutée
  plus tard, non traitée ici).
- Détails exacts de l'API Kick (endpoints, format de signature webhook,
  scopes disponibles) — à vérifier dans la doc `socialiteproviders/kick` et
  l'API Kick au moment de l'implémentation ; ce document décrit uniquement la
  structure côté application.
