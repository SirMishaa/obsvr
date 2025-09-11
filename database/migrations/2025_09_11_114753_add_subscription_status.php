<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favourite_streamers', function (Blueprint $table) {
            $table->enum('subscription_status', [
                'enabled',                                   // Subscription is active and working
                'webhook_callback_verification_pending',     // Waiting for callback URL verification
                'webhook_callback_verification_failed',      // Failed to verify callback URL
                'notification_failures_exceeded',            // Too many failed notification attempts
                'authorization_revoked',                     // The authorization was revoked
                'user_removed',                              // The subscribed user was removed
                'version_removed',                           // API version is no longer supported
                'unsubscribed',                              // No active subscription
                'pending',                                   // Initial status while creating a subscription
                'failed',                                    // General failure status
                'suspended',                                 // Subscription is temporarily suspended
            ])->default('unsubscribed');
        });
    }

    public function down(): void
    {
        Schema::table('favourite_streamers', function (Blueprint $table) {
            $table->dropColumn('subscription_status');
        });
    }
};
