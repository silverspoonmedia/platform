<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Domain\Identity\Events\AuthStateChanged;
use App\Domain\Identity\Events\RoleListChanged;
use App\Domain\Identity\Events\SecurityStateChanged;
use App\Domain\Identity\Events\UserListChanged;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\AuthStateBroadcast;
use App\Domain\Identity\Support\RbacRealtime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

/**
 * Covers the RbacRealtime list-ping extensions (UserListChanged/RoleListChanged)
 * and the AuthStateBroadcast personal-channel helper (auth/security events).
 */
class RbacAndAuthRealtimeTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    private function enableBroadcast(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher' => [
                'driver' => 'pusher',
                'key' => 'key',
                'secret' => 'secret',
                'app_id' => 'app',
                'options' => ['cluster' => 'ap1', 'port' => 443, 'scheme' => 'https'],
            ],
        ]);
    }

    private function disableBroadcast(): void
    {
        config(['broadcasting.default' => 'null']);
    }

    public function test_user_list_changed_noop_when_unconfigured(): void
    {
        Event::fake([UserListChanged::class]);
        $this->disableBroadcast();

        app(RbacRealtime::class)->userListChanged('created', 'user-1');

        Event::assertNotDispatched(UserListChanged::class);
    }

    public function test_user_list_changed_dispatches_when_configured(): void
    {
        Event::fake([UserListChanged::class]);
        $this->enableBroadcast();

        app(RbacRealtime::class)->userListChanged('updated', 'user-9');

        Event::assertDispatched(UserListChanged::class, function (UserListChanged $e): bool {
            return $e->action === 'updated'
                && $e->userId === 'user-9'
                && $e->broadcastAs() === 'rbac.user.list.changed'
                && $e->broadcastOn()[0]->name === 'private-rbac.users';
        });
    }

    public function test_role_list_changed_noop_when_unconfigured(): void
    {
        Event::fake([RoleListChanged::class]);
        $this->disableBroadcast();

        app(RbacRealtime::class)->roleListChanged('deleted', 'role-1');

        Event::assertNotDispatched(RoleListChanged::class);
    }

    public function test_role_list_changed_dispatches_when_configured(): void
    {
        Event::fake([RoleListChanged::class]);
        $this->enableBroadcast();

        app(RbacRealtime::class)->roleListChanged('created', 'role-3');

        Event::assertDispatched(RoleListChanged::class, function (RoleListChanged $e): bool {
            return $e->action === 'created'
                && $e->roleId === 'role-3'
                && $e->broadcastAs() === 'rbac.role.list.changed'
                && $e->broadcastOn()[0]->name === 'private-rbac.roles';
        });
    }

    public function test_auth_state_noop_when_unconfigured(): void
    {
        Event::fake([AuthStateChanged::class]);
        $this->disableBroadcast();

        $user = $this->makeUser();
        app(AuthStateBroadcast::class)->state($user, 'login', 'password');

        Event::assertNotDispatched(AuthStateChanged::class);
    }

    public function test_auth_state_dispatches_on_personal_channel(): void
    {
        Event::fake([AuthStateChanged::class]);
        $this->enableBroadcast();

        $user = $this->makeUser();
        app(AuthStateBroadcast::class)->state($user, 'login', 'password');

        Event::assertDispatched(AuthStateChanged::class, function (AuthStateChanged $e) use ($user): bool {
            return $e->userId === (string) $user->id
                && $e->action === 'login'
                && $e->loginMethod === 'password'
                && $e->broadcastAs() === 'auth.state.changed'
                && $e->broadcastOn()[0]->name === 'private-App.Models.User.'.$user->id;
        });
    }

    public function test_security_state_noop_when_unconfigured(): void
    {
        Event::fake([SecurityStateChanged::class]);
        $this->disableBroadcast();

        $user = $this->makeUser();
        app(AuthStateBroadcast::class)->security($user, 'password_changed', 'change');

        Event::assertNotDispatched(SecurityStateChanged::class);
    }

    public function test_security_state_dispatches_on_personal_channel(): void
    {
        Event::fake([SecurityStateChanged::class]);
        $this->enableBroadcast();

        $user = $this->makeUser();
        app(AuthStateBroadcast::class)->security($user, 'two_factor_enabled');

        Event::assertDispatched(SecurityStateChanged::class, function (SecurityStateChanged $e) use ($user): bool {
            return $e->userId === (string) $user->id
                && $e->action === 'two_factor_enabled'
                && $e->method === null
                && $e->broadcastAs() === 'auth.security.changed'
                && $e->broadcastOn()[0]->name === 'private-App.Models.User.'.$user->id;
        });
    }

    private function makeUser(): User
    {
        return User::factory()->create();
    }
}
