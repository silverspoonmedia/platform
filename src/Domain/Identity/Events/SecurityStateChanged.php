<?php

namespace App\Domain\Identity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired alongside PasswordChanged / TwoFactorEnabled / TwoFactorDisabled so
 * the owner's other tabs can refresh self state (e.g. 2FA status badge).
 * Personal channel only — action/method in payload, never credentials.
 */
class SecurityStateChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        /** @var 'password_changed'|'two_factor_enabled'|'two_factor_disabled' */
        public readonly string $action,
        /** @var 'change'|'reset'|null */
        public readonly ?string $method = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'auth.security.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'action' => $this->action,
            'method' => $this->method,
        ], fn ($v) => $v !== null);
    }
}
