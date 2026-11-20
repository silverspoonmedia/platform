<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kernel-owned copy of the host baseline.
 *
 * The host has its own `database/migrations/0001_01_01_000000_create_users_table.php`;
 * this file exists so a package test can build the Identity schema without the
 * monolith checkout, and so a consumer can publish it
 * (`php artisan vendor:publish --tag=silverspoon-platform-migrations`).
 *
 * The timestamp is deliberately one millisecond later than the host's: the
 * migrator keys a migration by its file name, and this test process loads the
 * host path first. An identical name would make the second run skip this file.
 *
 * Squashed baseline: `users` carries every column the app actually reads today
 * (locale, 2FA, soft deletes) instead of a create plus four alters.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            // Resolution order lives in SetLocale; null falls through to the header/cookie/config.
            $table->string('locale', 10)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            // Blind index over the envelope-encrypted token; see envelope-encryption.mdc.
            $table->string('token_blind_index', 64)->nullable();
            $table->index('token_blind_index');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
