<?php

namespace App\Enums;

enum TwitchSubscriptionStatus: string
{
    // Subscription is active and working
    case ENABLED = 'enabled';

    // Waiting for callback URL verification
    case WEBHOOK_CALLBACK_VERIFICATION_PENDING = 'webhook_callback_verification_pending';

    // Failed to verify callback URL
    case WEBHOOK_CALLBACK_VERIFICATION_FAILED = 'webhook_callback_verification_failed';

    // Too many failed notification attempts
    case NOTIFICATION_FAILURES_EXCEEDED = 'notification_failures_exceeded';

    // The authorization was revoked
    case AUTHORIZATION_REVOKED = 'authorization_revoked';

    // The subscribed user was removed
    case USER_REMOVED = 'user_removed';

    // API version is no longer supported
    case VERSION_REMOVED = 'version_removed';

    // No active subscription
    case UNSUBSCRIBED = 'unsubscribed';

    // Initial status while creating a subscription
    case PENDING = 'pending';

    // General failure status
    case FAILED = 'failed';

    // Subscription is temporarily suspended
    case SUSPENDED = 'suspended';

    public static function default(): self
    {
        return self::UNSUBSCRIBED;
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
