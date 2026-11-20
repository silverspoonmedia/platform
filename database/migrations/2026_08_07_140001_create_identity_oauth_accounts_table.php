<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kernel-owned copy of the host `identity_oauth_accounts` migration.
 *
 * See `0001_01_01_000001_create_users_table.php` for why the timestamps differ
 * from the host's copy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_oauth_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_user_id', 64);
            $table->text('access_token');
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->json('connections_snapshot')->nullable();
            $table->timestamp('connections_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'provider_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_oauth_accounts');
    }
};
