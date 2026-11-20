<?php

namespace App\Domain\Identity\Tests\Unit\Services\Rbac;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use App\Domain\Identity\Tests\TestCase;
use App\Support\Testing\Concerns\SeedsRolesAndPermissions;

class RoleServiceTest extends TestCase
{
    use SeedsRolesAndPermissions;

    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        // `users` and the pivots are kernel-wide and shared across the process;
        // a prior test left rows behind, and the seeded ladder is reused by
        // `updateOrCreate`. Start each test from empty user/pivot tables.
        $this->truncateTables('users', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions', 'role_has_permissions');
        $this->seedRolesAndPermissions();

        $this->service = app(RoleService::class);
    }

    public function test_core_levels_drive_lowest_level_and_assignable_roles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->assertSame(100, $this->service->getLowestRoleLevel($admin));

        $assignable = $this->service->getAssignableRoles($admin)->pluck('name')->all();

        $this->assertContains('Moderator', $assignable);
        $this->assertContains('Management', $assignable);
        $this->assertContains('User', $assignable);
        $this->assertNotContains('Root', $assignable);
        $this->assertNotContains('Admin', $assignable);
    }

    public function test_product_role_level_does_not_affect_core_hierarchy(): void
    {
        Role::query()->create([
            'name' => 'Playground Super',
            'guard_name' => 'api',
            'domain_kind' => DomainKind::Product,
            'level' => 1,
        ]);

        $user = User::factory()->create();
        $user->assignRole('User');
        $user->assignRole('Playground Super');

        $this->assertSame(400, $this->service->getLowestRoleLevel($user));
        $this->assertSame(1, $this->service->getLowestRoleLevelFor($user, DomainKind::Product));

        $target = User::factory()->create();
        $target->assignRole('Admin');

        $this->assertFalse($this->service->canActorViewTarget($user, $target));
        $this->assertFalse($this->service->allTargetRolesAboveActor($user, $target));
    }

    public function test_assignable_roles_exclude_product_roles(): void
    {
        Role::query()->create([
            'name' => 'Playground Editor',
            'guard_name' => 'api',
            'domain_kind' => DomainKind::Product,
            'level' => 200,
        ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $assignableNames = $this->service->getAssignableRoles($admin)->pluck('name')->all();

        $this->assertNotContains('Playground Editor', $assignableNames);
        $this->assertFalse($this->service->isRoleAssignableBy($admin, 'Playground Editor'));
        $this->assertTrue($this->service->isRoleAssignableBy($admin, 'Moderator'));
    }

    public function test_same_level_number_allowed_for_core_and_product(): void
    {
        $productRoot = Role::query()->create([
            'name' => 'Playground Root',
            'guard_name' => 'api',
            'domain_kind' => DomainKind::Product,
            'level' => 1,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'Root',
            'domain_kind' => DomainKind::Core->value,
            'level' => 1,
        ]);
        $this->assertDatabaseHas('roles', [
            'id' => $productRoot->id,
            'domain_kind' => DomainKind::Product->value,
            'level' => 1,
        ]);
    }

    public function test_user_with_only_product_roles_has_null_core_level(): void
    {
        Role::query()->create([
            'name' => 'Playground Member',
            'guard_name' => 'api',
            'domain_kind' => DomainKind::Product,
            'level' => 50,
        ]);

        $user = User::factory()->create();
        $user->assignRole('Playground Member');

        $this->assertNull($this->service->getLowestRoleLevel($user));
        $this->assertTrue($this->service->getAssignableRoles($user)->isEmpty());
    }
}
