<?php

namespace App\Support\Product\Support;

use App\Support\Broadcasting\BroadcastConnection;
use App\Support\Product\Events\ProductActivationChanged;

/**
 * Fire ProductActivation realtime events after state transitions.
 */
class ProductActivationRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function activationChanged(string $productKey, string $action, ?string $userId = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new ProductActivationChanged($productKey, $action, $userId));
    }
}
