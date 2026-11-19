<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Domain\Identity\Events\RolePermissionChanged;
use App\Domain\Identity\Support\RbacRealtime;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RbacRealtimeTest extends TestCase
{
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

    public function test_enabled_follows_broadcast_config(): void
    {
        $rt = app(RbacRealtime::class);

        $this->disableBroadcast();
        $this->assertFalse($rt->enabled());

        $this->enableBroadcast();
        $this->assertTrue($rt->enabled());
    }

    public function test_noop_when_unconfigured(): void
    {
        Event::fake([RolePermissionChanged::class]);
        $this->disableBroadcast();

        app(RbacRealtime::class)->changed('role_assigned', null, 'user-1');

        Event::assertNotDispatched(RolePermissionChanged::class);
    }

    public function test_dispatches_when_configured(): void
    {
        Event::fake([RolePermissionChanged::class]);
        $this->enableBroadcast();

        app(RbacRealtime::class)->changed('role_assigned', null, 'user-1');

        Event::assertDispatched(RolePermissionChanged::class, function (RolePermissionChanged $e): bool {
            return $e->action === 'role_assigned'
                && $e->userId === 'user-1';
        });
    }
}
