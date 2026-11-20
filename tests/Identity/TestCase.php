<?php

namespace App\Domain\Identity\Tests;

use App\Support\Testing\KernelSchema;
use App\Support\Testing\PackageTestCase;
use Spatie\Permission\PermissionRegistrar;

/**
 * Identity's harness: the kernel Testbench base.
 *
 * A package test never uses `RefreshDatabase` — the kernel schema is created
 * once per process by {@see KernelSchema}, and a rollback
 * over a shared in-memory connection would reach tables a sibling package
 * created. Clear owned rows with {@see self::truncateTables()}.
 */
abstract class TestCase extends PackageTestCase
{
    protected function truncateTables(string ...$tables): void
    {
        $connection = $this->app['db']->connection();

        foreach ($tables as $table) {
            $connection->table($table)->delete();
        }

        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
