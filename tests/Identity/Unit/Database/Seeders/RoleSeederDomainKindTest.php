<?php

namespace App\Domain\Identity\Tests\Unit\Database\Seeders;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleSeederDomainKindTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_seeder_sets_domain_kind_core_on_all_roles_and_permissions(): void
    {
        $this->seed(RoleSeeder::class);

        $this->assertSame(5, Role::query()->count());
        $this->assertSame(0, Role::query()->where('domain_kind', '!=', DomainKind::Core->value)->count());

        $this->assertGreaterThan(0, Permission::query()->count());
        $this->assertSame(
            0,
            Permission::query()->where('domain_kind', '!=', DomainKind::Core->value)->count(),
        );

        $this->assertDatabaseHas('roles', [
            'name' => 'Root',
            'domain_kind' => DomainKind::Core->value,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('permissions', [
            'name' => 'view user',
            'domain_kind' => DomainKind::Core->value,
        ]);
    }
}
