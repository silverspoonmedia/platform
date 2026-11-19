<?php

namespace App\Domain\Identity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired alongside the domain auth events (login/logout/2FA challenge) so the
 * owner's open sessions/views can soft-refetch self state. Personal channel
 * only — no sensitive payload beyond the action name.
 */
class AuthStateChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        /** @var 'login'|'logout'|'two_factor_challenge'|'login_failed' */
        public readonly string $action,
        public readonly ?string $loginMethod = null,
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
        return 'auth.state.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'action' => $this->action,
            'login_method' => $this->loginMethod,
        ], fn ($v) => $v !== null);
    }
}
