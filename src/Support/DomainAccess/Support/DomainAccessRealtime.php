<?php

namespace App\Support\DomainAccess\Support;

use App\Support\Broadcasting\BroadcastConnection;
use App\Support\DomainAccess\Events\DomainVisibilityChanged;

/**
 * Fire DomainAccess realtime events after visibility changes.
 */
class DomainAccessRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function visibilityChanged(string $productKey, string $visibility): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new DomainVisibilityChanged($productKey, $visibility));
    }
}
