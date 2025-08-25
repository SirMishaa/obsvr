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
        //
        Schema::table('users', function (Blueprint $table) {
            $table->enum('auth_provider', ['twitch'])->nullable();
            $table->string('auth_provider_id')->nullable();
            $table->string('auth_provider_access_token')->nullable()->after('remember_token');
            $table->string('auth_provider_refresh_token')->nullable()->after('auth_provider_access_token');
            $table->timestamp('auth_provider_expires_at')->nullable()->after('auth_provider_refresh_token');
            $table->string('avatar_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'auth_provider',
                'auth_provider_id',
                'avatar_url',
                'auth_provider_access_token',
                'auth_provider_refresh_token',
                'auth_provider_expires_at',
            ]);
        });
    }
};
