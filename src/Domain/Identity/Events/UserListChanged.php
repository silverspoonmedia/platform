<?php

namespace App\Domain\Identity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user list row may have changed (created/updated/deleted) — open user
 * tables soft-refetch. Ids-only invalidation ping, no PII in the payload.
 */
class UserListChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly ?string $userId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('rbac.users')];
    }

    public function broadcastAs(): string
    {
        return 'rbac.user.list.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'user_id' => $this->userId,
        ];
    }
}
