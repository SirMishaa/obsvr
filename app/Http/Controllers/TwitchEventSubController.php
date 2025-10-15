<?php

namespace App\Http\Controllers;

use App\Data\TwitchChannelUpdateMessageData;
use App\Data\TwitchStreamOnlineWebhookMessageData;
use App\Enums\TwitchSubscriptionStatus;
use App\Http\Requests\TwitchEventSubRequest;
use App\Models\FavouriteStreamer;
use App\Notifications\TwitchChannelUpdatedNotification;
use App\Notifications\TwitchStreamerStreamStartedNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class TwitchEventSubController extends Controller
{
    public function handle(TwitchEventSubRequest $webhookRequest): Response
    {
        $validated = $webhookRequest->validated();
        /**
         * @var "webhook_callback_verification"|"notification"|"revocation"|string|null $webhookType
         */
        $webhookType = $validated['_headers.type'] ?? $webhookRequest->header('Twitch-Eventsub-Message-Type');
        $challenge = $webhookRequest->jsonPayload['challenge'] ?? null;

        [$broadcasterUserId, $broadcasterUserName] = [
            Arr::string($validated, 'subscription.condition.broadcaster_user_id'),
            Arr::get($validated, 'event.broadcaster_user_name'),
        ];

        $favouriteStreamer = $broadcasterUserId ? FavouriteStreamer::where('streamer_id', $broadcasterUserId)->orWhere([
            'streamer_name' => $broadcasterUserName,
        ])->first() : null;

        if ($broadcasterUserId && ! $favouriteStreamer) {
            Log::error('Received Twitch EventSub webhook for unknown favourite streamer:', [
                'broadcaster_user_id' => $broadcasterUserId,
                'broadcaster_user_name' => $broadcasterUserName,
            ]);
        }

        switch ($webhookType) {
            case 'webhook_callback_verification':
                Log::info('Twitch EventSub verification request received:', [
                    'challenge' => $challenge,
                ]);

                if ($favouriteStreamer) {
                    $favouriteStreamer->update(['subscription_status' => TwitchSubscriptionStatus::WEBHOOK_CALLBACK_VERIFICATION_PENDING]);
                    Log::debug('Updated favourite streamer subscription status to webhook_callback_verification_pending', [
                        'broadcaster_user_id' => $broadcasterUserId,
                    ]);
                }

                return response((string) $challenge, 200)->header('Content-Type', 'text/plain');

            case 'revocation':
                Log::warning('Twitch EventSub subscription revoked:', [
                    'subscription' => $validated['subscription'],
                ]);

                if ($favouriteStreamer) {
                    $favouriteStreamer->update(['subscription_status' => TwitchSubscriptionStatus::AUTHORIZATION_REVOKED]);
                    Log::debug('Updated favourite streamer subscription status to authorization_revoked', [
                        'broadcaster_user_id' => $broadcasterUserId,
                    ]);
                }

                return response()->noContent();

            case 'notification':
                /** @var array<string, mixed> $validatedEvent */
                $validatedEvent = Arr::array($validated, 'event');
                $eventType = Arr::string($validated, 'subscription.type', '');

                Log::info('Twitch EventSub notification received:', [
                    'event_type' => $eventType,
                    'subscription' => $validated['subscription'],
                ]);

                match ($eventType) {
                    'stream.online' => $this->handleStreamOnlineEvent($validatedEvent),
                    'stream.offline' => $this->handleStreamOfflineEvent($validatedEvent),
                    'channel.update' => $this->handleChannelUpdateEvent($validatedEvent),
                    default => Log::warning('Unhandled Twitch EventSub subscription type:', [
                        'type' => $eventType,
                    ]),
                };

                return response()->noContent();

            default:
                Log::error('Unknown Twitch EventSub webhook type:', [
                    'type' => $webhookType,
                ]);

                return response('Unknown Twitch EventSub event type: '.$webhookType, 400)
                    ->header('Content-Type', 'text/plain');
        }
    }

    private function handleStreamOnlineEvent(array $subscription): void
    {
        $streamOnlineMessage = TwitchStreamOnlineWebhookMessageData::from($subscription);

        $favouriteStreamers = FavouriteStreamer::where('streamer_id', $streamOnlineMessage->broadcasterUserId)
            ->orWhere('streamer_name', $streamOnlineMessage->broadcasterUserName)
            ->with('user')
            ->get();

        Log::info(sprintf('Streamer %s is now online from %s, notifying %d users',
            $streamOnlineMessage->broadcasterUserName,
            $streamOnlineMessage->startedAt->diffForHumans(),
            $favouriteStreamers->count()));

        $favouriteStreamers->each(
            function (FavouriteStreamer $favouriteStreamer) {
                $favouriteStreamer->update(['subscription_status' => TwitchSubscriptionStatus::ENABLED]);
                $favouriteStreamer->user->notifyNow(new TwitchStreamerStreamStartedNotification($favouriteStreamer->streamer_name));
            }
        );
    }

    private function handleStreamOfflineEvent(array $subscription): void
    {
        Log::info('Stream offline event received:', [
            'subscription' => $subscription,
        ]);
    }

    private function handleChannelUpdateEvent(array $subscription): void
    {

        $channelUpdateMessage = TwitchChannelUpdateMessageData::from($subscription);

        $favouriteStreamers = FavouriteStreamer::where('streamer_id', $channelUpdateMessage->broadcasterUserId)
            ->orWhere('streamer_name', $channelUpdateMessage->broadcasterUserName)
            ->with('user')
            ->get();

        Log::info(sprintf('Streamer %s updated their channel/stream, notifying %d users',
            $channelUpdateMessage->broadcasterUserName,
            $favouriteStreamers->count()));

        Log::info('Channel update event received:', [
            'subscription' => $channelUpdateMessage,
        ]);

        $favouriteStreamers->each(
            function (FavouriteStreamer $favouriteStreamer) use ($channelUpdateMessage) {
                $favouriteStreamer->subscriptions()->where('type', 'channel.update')->update(['status' => TwitchSubscriptionStatus::ENABLED]);
                $favouriteStreamer->user->notifyNow(new TwitchChannelUpdatedNotification($channelUpdateMessage));
            }
        );
    }
}
