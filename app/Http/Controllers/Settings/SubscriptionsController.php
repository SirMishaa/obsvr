<?php

namespace App\Http\Controllers\Settings;

use App\Data\TwitchEventSubSubscriptionItemData;
use App\Enums\TwitchSubscriptionStatus;
use App\Enums\TwitchSubscriptionType;
use App\Http\Controllers\Controller;
use App\Models\FavouriteStreamer;
use App\Services\TwitchApiClient;
use App\Services\TwitchTokenManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Log;

class SubscriptionsController extends Controller
{
    public function __construct(
        private readonly TwitchApiClient $twitchApiClient,
        private readonly TwitchTokenManagerService $twitchTokenManagerService
    ) {}

    public function edit(Request $request): Response
    {

        $favouriteStreamers = $request
            ->user()
            ?->favouriteStreamers()
            ->with('subscriptions')
            ->get();

        return Inertia::render('settings/Subscription', [
            'status' => $request->session()->get('status'),
            'favouriteStreamers' => $favouriteStreamers,
        ]);
    }

    public function update(FavouriteStreamer $favouriteStreamer, Request $request): RedirectResponse
    {
        /**
         * The event types to subscribe to/to keep being subscribed. (e.e. stream.online, stream.offline, channel.update)
         */
        $validatedEvents = $request->validate([
            'types' => ['present', 'array'],
            'types.*' => ['string', Rule::in(TwitchSubscriptionType::values())],
            'batch_delay' => ['nullable', 'integer', Rule::in([0, 60, 120, 300, 600])],
        ]);

        $appAccessToken = $this->twitchTokenManagerService->ensureFreshAppAccessToken();
        /** @var Collection<int, TwitchEventSubSubscriptionItemData> $currentSubscriptions */
        $currentSubscriptions = $this->twitchApiClient->fetchSubscriptions($favouriteStreamer->streamer_id, $appAccessToken)->data->toCollection();

        /**
         * Events to remove subscriptions for (i.e. events that are in the current subscriptions but not in the validatedEvents array)
         *
         * @var Collection<int, TwitchEventSubSubscriptionItemData> $subscriptionToRemove
         */
        $subscriptionToRemove = $currentSubscriptions
            ->filter(fn (TwitchEventSubSubscriptionItemData $subscription) => ! in_array($subscription->type, $validatedEvents['types'], true));

        /**
         * Events to add subscriptions for (i.e. events that are in the validatedEvents array but not in the current subscriptions)
         *
         * @var array<int, string> $subscriptionToAdd
         */
        $subscriptionToAdd = array_diff($validatedEvents['types'], $currentSubscriptions->pluck('type')->toArray());

        foreach ($subscriptionToRemove as $subToRemove) {
            $this->twitchApiClient->deleteSubscription($subToRemove->id, $appAccessToken);
            $favouriteStreamer->subscriptions()->where('type', $subToRemove->type)->delete();
            Log::debug(sprintf('Unsubscribed from "%s" event subscription for streamer %s', $subToRemove->type, $favouriteStreamer->streamer_name));
        }

        foreach ($subscriptionToAdd as $subToAdd) {
            $favouriteStreamer->subscriptions()->firstOrCreate([
                'type' => $subToAdd,
            ], [
                'status' => TwitchSubscriptionStatus::PENDING,
            ]);
            $this->twitchApiClient->createSubscription($favouriteStreamer->streamer_id, $appAccessToken, $subToAdd);

            Log::debug(sprintf('Subscribed to "%s" event subscription for streamer %s', $subToAdd, $favouriteStreamer->streamer_name));
        }

        if (array_key_exists('batch_delay', $validatedEvents)) {
            $favouriteStreamer->subscriptions()
                ->where('type', 'channel.update')
                ->update(['batch_delay' => $validatedEvents['batch_delay']]);
        }

        return back(status: 303);
    }
}
