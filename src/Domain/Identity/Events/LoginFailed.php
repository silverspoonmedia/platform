<?php

namespace App\Domain\Identity\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LoginFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $email,
        public string $ipAddress,
        public ?string $userAgent = null,
    ) {}
}
