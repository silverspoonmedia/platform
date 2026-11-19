<?php

namespace App\Domain\Identity\Support;

use App\Domain\Identity\Events\AuthStateChanged;
use App\Domain\Identity\Events\SecurityStateChanged;
use App\Domain\Identity\Models\User;
use App\Support\Broadcasting\BroadcastConnection;

/**
 * Thin gate around personal-channel auth/security broadcast events.
 *
 * Gated on {@see BroadcastConnection::isConfigured()} only.
 */
class AuthStateBroadcast
{
    public function enabled(): bool
    {
        return BroadcastConnection::isConfigured();
    }

    /**
     * @param  'login'|'logout'|'two_factor_challenge'|'login_failed'  $action
     */
    public function state(User $user, string $action, ?string $loginMethod = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new AuthStateChanged((string) $user->id, $action, $loginMethod));
    }

    /**
     * @param  'password_changed'|'two_factor_enabled'|'two_factor_disabled'  $action
     * @param  'change'|'reset'|null  $method
     */
    public function security(User $user, string $action, ?string $method = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        event(new SecurityStateChanged((string) $user->id, $action, $method));
    }
}
