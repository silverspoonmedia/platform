<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Events\RoleListChanged;
use App\Domain\Identity\Events\RolePermissionChanged;
use App\Domain\Identity\Events\UserListChanged;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Fire RBAC realtime events after role/permission/user mutations.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only.
 */
class RbacRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function changed(string $action, ?string $roleId = null, ?string $userId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new RolePermissionChanged($action, $roleId, $userId));
    }

    /**
     * User list row created/updated/deleted — open user tables reload.
     */
    public function userListChanged(string $action, ?string $userId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new UserListChanged($action, $userId));
    }

    /**
     * Role rows/levels changed — RBAC role views reload.
     */
    public function roleListChanged(string $action, ?string $roleId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new RoleListChanged($action, $roleId));
    }
}
