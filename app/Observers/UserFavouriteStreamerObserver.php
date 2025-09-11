<?php

namespace App\Observers;

use App\Data\TwitchEventSubSubscriptionItemData;
use App\Models\FavouriteStreamer;
use App\Services\TwitchApiClient;
use App\Services\TwitchTokenManagerService;
use Illuminate\Support\Facades\Log;
use Throwable;

readonly class UserFavouriteStreamerObserver
{
    public function __construct(
        private TwitchApiClient $twitchApiClient,
        private TwitchTokenManagerService $tokens,
    ) {}

    /**
     * Handles the "created" event for a FavouriteStreamer instance.
     *
     * This method ensures that the necessary EventSub subscription for the streamer
     * is created or recreated if needed. If a token is unavailable, the method simply returns.
     *
     * Attempts to delete existing subscriptions for the streamer before creating
     * a new one.
     *
     * @param  FavouriteStreamer  $favouriteStreamer  The FavouriteStreamer instance for which the subscription should be handled.
     */
    public function created(FavouriteStreamer $favouriteStreamer): void
    {
        if (! $token = $this->getToken()) {
            return;
        }

        try {
            $deleted = $this->deleteAllSubscriptions($favouriteStreamer, $token);

            $response = $this->twitchApiClient->createSubscription($favouriteStreamer->streamer_id, $token);
            $status = $response['data'][0]['status'] ?? null;

            $favouriteStreamer->forceFill(['subscription_status' => $status])->save();
            Log::info(sprintf(
                '[%s] Ensured EventSub stream.online for %s (%s) — %d deleted, new status=%s',
                self::class, $favouriteStreamer->streamer_name, $favouriteStreamer->streamer_id, $deleted, $status ?? 'unknown'
            ));
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[%s] Failed to (re)create EventSub for %s (%s): %s',
                self::class, $favouriteStreamer->streamer_name, $favouriteStreamer->streamer_id, $e->getMessage()
            ), ['exception' => $e]);
        }
    }

    /**
     * Handles the deletion of all subscriptions for a given favourite streamer.
     *
     * Attempts to retrieve a token and delete all related EventSub subscriptions
     * for the provided favourite streamer. Logs information about the number of
     * deleted subscriptions or any errors encountered during the process.
     *
     * @param  FavouriteStreamer  $favouriteStreamer  The favourite streamer for which subscriptions are being deleted.
     */
    public function deleted(FavouriteStreamer $favouriteStreamer): void
    {
        if (! $token = $this->getToken()) {
            return;
        }

        try {
            $deleted = $this->deleteAllSubscriptions($favouriteStreamer, $token);
            Log::info(sprintf(
                '[%s] Removed EventSub for %s (%s) — %d deleted',
                self::class, $favouriteStreamer->streamer_name, $favouriteStreamer->streamer_id, $deleted
            ));
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[%s] Failed to delete EventSub for %s (%s): %s',
                self::class, $favouriteStreamer->streamer_name, $favouriteStreamer->streamer_id, $e->getMessage()
            ), ['exception' => $e]);
        }
    }

    private function getToken(): ?string
    {
        $token = $this->tokens->ensureFreshAppAccessToken();
        if (! $token) {
            Log::error(sprintf('[%s] No app access token returned', self::class));
        }

        return $token;
    }

    private function deleteAllSubscriptions(FavouriteStreamer $fav, string $token): int
    {
        $resp = $this->twitchApiClient->fetchSubscriptions($fav->streamer_id, $token);

        $deleted = 0;
        foreach ($resp->data->toCollection() as $sub) {
            /** @var TwitchEventSubSubscriptionItemData $sub */
            $this->twitchApiClient->deleteSubscription($sub->id, $token);
            $deleted++;
            Log::info(sprintf(
                '[%s] Deleted EventSub %s for streamer %s',
                self::class, $sub->id, $fav->streamer_name
            ));
        }

        return $deleted;
    }
}
