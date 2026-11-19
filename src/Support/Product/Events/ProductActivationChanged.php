<?php

namespace App\Support\Product\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Product activation state may have changed — admin list soft-refetch.
 */
class ProductActivationChanged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $productKey,
        public readonly string $action,
        public readonly ?string $userId = null,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('product-activations')];
    }

    public function broadcastAs(): string
    {
        return 'product.activation.changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'product_key' => $this->productKey,
            'action' => $this->action,
            'user_id' => $this->userId,
        ];
    }
}
