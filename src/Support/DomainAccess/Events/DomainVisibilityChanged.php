<?php

namespace App\Support\DomainAccess\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Domain visibility may have changed — admin domain-access view soft-refetch.
 */
class DomainVisibilityChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $productKey,
        public readonly string $visibility,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('domain-access')];
    }

    public function broadcastAs(): string
    {
        return 'domain.visibility.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_key' => $this->productKey,
            'visibility' => $this->visibility,
        ];
    }
}
