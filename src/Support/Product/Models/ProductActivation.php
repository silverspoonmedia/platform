<?php

namespace App\Support\Product\Models;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Enums\ProductActivationType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductActivation extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'activation_type' => ProductActivationType::class,
            'state' => ProductActivationState::class,
            'provisioning_payload' => 'array',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('state', ProductActivationState::Active->value);
    }

    public function scopeForProduct($query, string $productKey)
    {
        return $query->where('product_key', $productKey);
    }
}
