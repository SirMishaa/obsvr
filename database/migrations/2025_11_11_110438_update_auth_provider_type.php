<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('auth_provider_access_token')->nullable()->change();
            $table->text('auth_provider_refresh_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('auth_provider_access_token')->nullable()->change();
            $table->string('auth_provider_refresh_token')->nullable()->change();
        });
    }
};
