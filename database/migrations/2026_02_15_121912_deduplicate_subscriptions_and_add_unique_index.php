<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Delete duplicate subscriptions, keeping the one with the highest id per (favourite_streamer_id, type)
        DB::statement('
            DELETE FROM subscriptions
            WHERE id NOT IN (
                SELECT max_id FROM (
                    SELECT MAX(id) as max_id
                    FROM subscriptions
                    GROUP BY favourite_streamer_id, type
                ) as keep_ids
            )
        ');

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['favourite_streamer_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['favourite_streamer_id', 'type']);
        });
    }
};
