<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedSmallInteger('lol_reminder_minutes')->nullable()->default(20);
        });

        Schema::create('lol_league_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('league');
            $table->string('mode')->default('all');
            $table->json('team_codes')->nullable();
            $table->string('costream_channel')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'league']);
        });

        Schema::create('lol_matches', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('league')->index();
            $table->dateTime('start_time')->index();
            $table->string('state');
            $table->string('block_name')->nullable();
            $table->unsignedTinyInteger('best_of')->nullable();
            $table->json('teams');
            $table->dateTime('watch_ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lol_match_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('lol_match_id');
            $table->foreign('lol_match_id')->references('id')->on('lol_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->dateTime('sent_at');

            $table->unique(['lol_match_id', 'user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lol_match_notifications');
        Schema::dropIfExists('lol_matches');
        Schema::dropIfExists('lol_league_subscriptions');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('lol_reminder_minutes');
        });
    }
};
