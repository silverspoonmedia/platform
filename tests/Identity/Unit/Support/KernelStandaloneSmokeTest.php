<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Tests\TestCase;
use App\Support\Testing\Concerns\SeedsRolesAndPermissions;
use App\Support\Testing\KernelSchema;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

/**
 * The standalone harness contract: this file must pass with nothing but
 * `silverspoonmedia/platform` installed.
 *
 * It asserts three things a published tag owes a consumer — the kernel boots
 * without the monolith, the kernel schema exists, and the domain services are
 * bound. A regression here means a mirrored tag cannot run its own suite.
 */
class KernelStandaloneSmokeTest extends TestCase
{
    use SeedsRolesAndPermissions;

    #[Test]
    public function the_kernel_boots_without_the_monolith(): void
    {
        $this->assertSame('testing', $this->app->environment());
        $this->assertTrue($this->app->bound('db'));
        $this->assertTrue($this->app->bound(UserRepositoryInterface::class));
    }

    #[Test]
    public function the_kernel_schema_is_present(): void
    {
        foreach (KernelSchema::TABLES as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Kernel table [{$table}] is missing from the standalone harness.",
            );
        }
    }

    #[Test]
    public function a_kernel_model_round_trips(): void
    {
        $user = User::factory()->create(['email' => 'standalone@example.com']);

        $this->assertDatabaseHas('users', ['email' => 'standalone@example.com']);
        $this->assertSame($user->id, User::query()->findOrFail($user->id)->id);

        $this->truncateTables('users');
    }

    #[Test]
    public function the_core_role_ladder_seeds(): void
    {
        $this->seedRolesAndPermissions();

        $this->assertDatabaseHas('roles', ['name' => 'Admin', 'guard_name' => 'api']);
        $this->assertDatabaseHas('permissions', ['name' => 'view user', 'guard_name' => 'api']);

        $this->truncateTables('role_has_permissions', 'model_has_roles', 'model_has_permissions', 'permissions', 'roles');
    }
}
