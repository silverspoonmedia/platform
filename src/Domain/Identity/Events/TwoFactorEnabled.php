<?php

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwoFactorEnabled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public string $ipAddress,
        public ?string $userAgent = null,
    ) {}
}
