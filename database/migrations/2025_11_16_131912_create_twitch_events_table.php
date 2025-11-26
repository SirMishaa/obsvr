<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('twitch_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->nullable()->unique();
            $table->string('event_type')->index();
            $table->string('streamer_id')->nullable()->index();
            $table->string('streamer_name')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at')->index();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['streamer_id', 'event_type', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twitch_events');
    }
};
