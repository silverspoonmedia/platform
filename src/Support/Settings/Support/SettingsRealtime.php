<?php

namespace App\Support\Settings\Support;

use App\Support\Broadcasting\BroadcastConnection;
use App\Support\Settings\Events\SettingsGroupChanged;

/**
 * Fire Settings realtime events after successful mutations.
 */
class SettingsRealtime
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    public function groupChanged(string $group, string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new SettingsGroupChanged($group, $action));
    }
}
