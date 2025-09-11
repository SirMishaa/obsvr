<?php

namespace App\Http\Controllers;

use App\Data\TwitchStreamOnlineWebhookMessageData;
use App\Enums\TwitchSubscriptionStatus;
use App\Http\Requests\TwitchEventSubRequest;
use App\Models\FavouriteStreamer;
use App\Notifications\TwitchStreamerStreamStartedNotification;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TwitchEventSubController extends Controller
{
    public function handle(TwitchEventSubRequest $webhookRequest): Response
    {
        $validated = $webhookRequest->validated();
        /**
         * @var "webhook_callback_verification"|"notification"|"revocation"|string|null $eventType
         */
        $eventType = $validated['_headers.type'] ?? $webhookRequest->header('Twitch-Eventsub-Message-Type');
        $challenge = $webhookRequest->jsonPayload['challenge'] ?? null;

        $broadcasterUserId = $validated['subscription']['condition']['broadcaster_user_id'] ?? null;
        $favouriteStreamer = $broadcasterUserId ? FavouriteStreamer::where('streamer_id', $broadcasterUserId)->first() : null;

        switch ($eventType) {
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
                // Handle event depending on subscription.type
                Log::info('Twitch EventSub notification received:', [
                    'subscription' => $validated['subscription'],
                    'event' => $validated['event'],
                ]);

                if ($validated['subscription']['type'] === 'stream.online') {
                    $this->handleStreamOnlineEvent($validated['event']);
                }

                return response()->noContent();

            default:
                Log::error('Unknown Twitch EventSub event type:', [
                    'type' => $eventType,
                ]);

                return response('Unknown Twitch EventSub event type: '.$eventType, 400)
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
}
