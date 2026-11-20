<?php

namespace App\Support\Testing\Concerns;

use App\Domain\Identity\Database\Seeders\RoleSeeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the core role ladder, optionally with the product role ladder.
 *
 * Lives in the kernel so a package test can use it without the monolith's
 * `Tests\Concerns` tree — that namespace is host-owned and never ships. The
 * notification type settings are *not* seeded here: they belong to
 * `silverspoonmedia/notification`, and the kernel may not name a sibling
 * package. A test that needs them seeds that seeder itself.
 */
trait SeedsRolesAndPermissions
{
    protected function seedRolesAndPermissions(): void
    {
        // Spatie caches the permission table for a century-scale TTL; a fresh
        // Testbench application still answers from the previous one's entries,
        // so an empty cache is what makes the seeded ladder visible.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(RoleSeeder::class);
    }
}
