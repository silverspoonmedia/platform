<?php

namespace App\Domain\Identity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Role or permission assignment may have changed — RBAC views soft-refetch.
 */
class RolePermissionChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $roleId = null,
        public readonly ?string $userId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('rbac')];
    }

    public function broadcastAs(): string
    {
        return 'rbac.role.permission.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'role_id' => $this->roleId,
            'user_id' => $this->userId,
        ];
    }
}
