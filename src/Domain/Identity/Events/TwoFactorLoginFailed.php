<?php

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwoFactorLoginFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $ipAddress,
        public string $reason,
        public ?string $userAgent = null,
        public ?User $user = null,
    ) {}
}
