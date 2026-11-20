<?php

namespace App\Support\Testing;

use Illuminate\Support\Facades\Schema;

/**
 * The kernel-owned slice of the host database schema, for a package test.
 *
 * A core package ships code and models, not the host's migrations: `users`, the
 * permission tables, `system_settings` and the product tables belong to the
 * application the package is installed into. A package's own test still needs
 * those tables, and it must not reach into the monolith's `database/migrations`
 * to get them — that is exactly the coupling that made a published tag
 * unrunnable.
 *
 * This class builds the kernel's part from the migrations shipped in
 * `packages/platform/database/migrations` (the same files a host can publish with
 * `php artisan vendor:publish --tag=silverspoon-platform-migrations`) plus the
 * permission tables, inlined here because Spatie's migration is a published
 * vendor file the host owns and a package test has no host to publish from.
 * Keep the inline copy in step with
 * `database/migrations/2026_06_02_083740_create_permission_tables.php`.
 *
 * `Schema::hasTable()` is the idempotency guard: under PHPUnit every table
 * created inside one process is still present when the next test class boots,
 * and running the migrator per class would cost minutes in the serial run.
 */
final class KernelSchema
{
    /**
     * Tables this class creates, in creation order. `users` leads because every
     * other table either references it or is seeded against it.
     *
     * @var list<string>
     */
    public const TABLES = [
        'users',
        'password_reset_tokens',
        'sessions',
        'permissions',
        'roles',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'system_settings',
        'identity_oauth_accounts',
    ];

    /**
     * Create every kernel table that does not exist yet.
     */
    public static function ensure(): void
    {
        if (Schema::hasTable('users')) {
            return;
        }

        foreach (['0001_01_01_000001_create_users_table', '2026_08_02_000001_create_system_settings_table', '2026_08_07_140001_create_identity_oauth_accounts_table'] as $name) {
            $migration = require __DIR__.'/../../../database/migrations/'.$name.'.php';
            $migration->up();
        }

        self::createPermissionTables();
        self::createCacheTables();
    }

    /**
     * The framework's `cache`/`cache_locks` tables — the `Localization` tests
     * force `cache.default = database` to prove a serializing store round-trips.
     * Mirrors `database/migrations/0001_01_01_000001_create_cache_table.php`.
     */
    private static function createCacheTables(): void
    {
        if (Schema::hasTable('cache')) {
            return;
        }

        Schema::create('cache', static function ($table): void {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', static function ($table): void {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    /**
     * Spatie's permission tables, matching the host baseline: UUID pivots,
     * `domain_kind` (core|product) and the nullable `level` ladder.
     */
    private static function createPermissionTables(): void
    {
        $teams = (bool) config('permission.teams');
        $tableNames = (array) config('permission.table_names');
        $columnNames = (array) config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
        $modelKey = $columnNames['model_morph_key'] ?? 'model_id';
        $teamKey = $columnNames['team_foreign_key'] ?? 'team_id';

        Schema::create($tableNames['permissions'] ?? 'permissions', static function ($table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('guard_name');
            $table->string('domain_kind', 32)->default('core');
            $table->timestamps();

            $table->index('domain_kind');
            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'] ?? 'roles', static function ($table) use ($teams, $teamKey): void {
            $table->uuid('id')->primary();
            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($teamKey)->nullable();
                $table->index($teamKey, 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->string('domain_kind', 32)->default('core');
            // Null keeps beta-tester roles out of the ladder.
            $table->unsignedInteger('level')->nullable();
            $table->timestamps();

            $table->index('domain_kind');
            if ($teams || config('permission.testing')) {
                $table->unique([$teamKey, 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
            $table->unique(['domain_kind', 'level']);
        });

        Schema::create($tableNames['model_has_permissions'] ?? 'model_has_permissions', static function ($table) use ($tableNames, $modelKey, $pivotPermission, $teams, $teamKey): void {
            $table->uuid($pivotPermission);
            $table->string('model_type');
            $table->uuid($modelKey);
            $table->index([$modelKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->cascadeOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($teamKey);
                $table->index($teamKey, 'model_has_permissions_team_foreign_key_index');
                $table->primary([$teamKey, $pivotPermission, $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $modelKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($tableNames['model_has_roles'] ?? 'model_has_roles', static function ($table) use ($tableNames, $modelKey, $pivotRole, $teams, $teamKey): void {
            $table->uuid($pivotRole);
            $table->string('model_type');
            $table->uuid($modelKey);
            $table->index([$modelKey, 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->cascadeOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($teamKey);
                $table->index($teamKey, 'model_has_roles_team_foreign_key_index');
                $table->primary([$teamKey, $pivotRole, $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $modelKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'] ?? 'role_has_permissions', static function ($table) use ($tableNames, $pivotRole, $pivotPermission): void {
            $table->uuid($pivotPermission);
            $table->uuid($pivotRole);
            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'] ?? 'permissions')
                ->cascadeOnDelete();
            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'] ?? 'roles')
                ->cascadeOnDelete();
            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });
    }
}
