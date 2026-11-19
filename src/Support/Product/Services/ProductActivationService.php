<?php

namespace App\Support\Product\Services;

use App\Domain\Identity\Models\User;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Enums\ProductActivationType;
use App\Support\Product\Events\ProductActivated;
use App\Support\Product\Events\ProductActivationStateChanged;
use App\Support\Product\Exceptions\ProductActivationException;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Support\ProductActivationRealtime;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductActivationService
{
    public function __construct(
        private readonly ProductRegistry $registry,
        private readonly ProductActivationRealtime $realtime,
    ) {}

    public function find(string $userId, string $productKey): ?ProductActivation
    {
        $this->assertKnown($productKey);

        return ProductActivation::query()
            ->where('user_id', $userId)
            ->where('product_key', $productKey)
            ->first();
    }

    public function findOrFail(string $userId, string $productKey): ProductActivation
    {
        $activation = $this->find($userId, $productKey);

        if ($activation === null) {
            throw new ProductNotFoundException(__('product-activations.not_found', ['product' => $productKey]));
        }

        return $activation;
    }

    public function isActive(string $userId, string $productKey): bool
    {
        $activation = $this->find($userId, $productKey);

        return $activation !== null && $activation->state === ProductActivationState::Active;
    }

    /**
     * Idempotent implicit activation. Used on register and on first middleware
     * miss for backward-compat (lazy create path). Creates Active row if absent.
     */
    public function activateImplicit(User $user, string $productKey, string $source = 'register_hook'): ProductActivation
    {
        $this->assertKnown($productKey);
        $this->assertType($productKey, ProductActivationType::Implicit);

        return DB::transaction(function () use ($user, $productKey, $source): ProductActivation {
            $existing = $this->find($user->id, $productKey);

            if ($existing !== null) {
                if ($existing->state === ProductActivationState::Active) {
                    return $existing;
                }

                return $this->transition($existing, ProductActivationState::Active, $source);
            }

            $activation = ProductActivation::create([
                'user_id' => $user->id,
                'product_key' => $productKey,
                'activation_type' => ProductActivationType::Implicit,
                'state' => ProductActivationState::Active,
                'activated_at' => now(),
                'source' => $source,
            ]);

            ProductActivated::dispatch($activation);

            return $activation;
        });
    }

    /**
     * Create a Pending explicit activation awaiting provisioning/approval.
     */
    public function requestExplicit(User $user, string $productKey, array $payload = [], string $source = 'manual'): ProductActivation
    {
        $this->assertKnown($productKey);
        $this->assertType($productKey, ProductActivationType::Explicit);

        return DB::transaction(function () use ($user, $productKey, $payload, $source): ProductActivation {
            $existing = $this->find($user->id, $productKey);

            if ($existing !== null) {
                if ($existing->state === ProductActivationState::Pending) {
                    return $existing;
                }

                if ($existing->state === ProductActivationState::Active) {
                    throw new ProductActivationException(__('product-activations.already_active'));
                }

                return $this->transition($existing, ProductActivationState::Pending, $source, $payload);
            }

            $activation = ProductActivation::create([
                'user_id' => $user->id,
                'product_key' => $productKey,
                'activation_type' => ProductActivationType::Explicit,
                'state' => ProductActivationState::Pending,
                'provisioning_payload' => $payload ?: null,
                'source' => $source,
            ]);

            return $activation;
        });
    }

    public function approve(ProductActivation $activation, array $payload = [], string $source = 'admin'): ProductActivation
    {
        if (! $activation->state->canApprove()) {
            throw new ProductActivationException(__('product-activations.cannot_approve', ['state' => $activation->state->value]));
        }

        return DB::transaction(function () use ($activation, $payload, $source): ProductActivation {
            $updated = $this->transition($activation, ProductActivationState::Active, $source, $payload);
            $updated->forceFill(['activated_at' => now()])->save();

            ProductActivated::dispatch($updated);

            return $updated->refresh();
        });
    }

    public function reject(ProductActivation $activation, string $reason, string $source = 'admin'): ProductActivation
    {
        if (! $activation->state->canExpire()) {
            throw new ProductActivationException(__('product-activations.cannot_reject', ['state' => $activation->state->value]));
        }

        return DB::transaction(function () use ($activation, $reason, $source): ProductActivation {
            $updated = $this->transition(
                $activation,
                ProductActivationState::Expired,
                $source,
                array_merge($activation->provisioning_payload ?? [], ['rejection_reason' => $reason]),
            );
            $updated->forceFill(['expired_at' => now()])->save();

            return $updated->refresh();
        });
    }

    public function suspend(ProductActivation $activation, ?string $reason = null, string $source = 'admin'): ProductActivation
    {
        if (! $activation->state->canSuspend()) {
            throw new ProductActivationException(__('product-activations.cannot_suspend', ['state' => $activation->state->value]));
        }

        return DB::transaction(function () use ($activation, $reason, $source): ProductActivation {
            $payload = $reason !== null
                ? array_merge($activation->provisioning_payload ?? [], ['suspension_reason' => $reason])
                : $activation->provisioning_payload;

            $updated = $this->transition($activation, ProductActivationState::Suspended, $source, $payload);
            $updated->forceFill(['suspended_at' => now()])->save();

            return $updated->refresh();
        });
    }

    public function reactivate(ProductActivation $activation, string $source = 'admin'): ProductActivation
    {
        if (! $activation->state->canReactivate()) {
            throw new ProductActivationException(__('product-activations.cannot_reactivate', ['state' => $activation->state->value]));
        }

        return DB::transaction(function () use ($activation, $source): ProductActivation {
            $updated = $this->transition($activation, ProductActivationState::Active, $source);
            $updated->forceFill(['suspended_at' => null])->save();

            return $updated->refresh();
        });
    }

    public function markExpired(ProductActivation $activation, string $source = 'scheduler'): ProductActivation
    {
        if (! $activation->state->canExpire()) {
            return $activation;
        }

        return DB::transaction(function () use ($activation, $source): ProductActivation {
            $updated = $this->transition($activation, ProductActivationState::Expired, $source);
            $updated->forceFill(['expired_at' => now()])->save();

            return $updated->refresh();
        });
    }

    /**
     * Lazy-create an implicit activation on first access. Called by middleware
     * when no row exists yet (backward-compat path for pre-registry users).
     */
    public function ensureImplicitActive(User $user, string $productKey): ProductActivation
    {
        $activation = $this->find($user->id, $productKey);

        if ($activation !== null) {
            return $activation;
        }

        Log::info('product.activation.lazy_create', [
            'user_id' => $user->id,
            'product_key' => $productKey,
        ]);

        return $this->activateImplicit($user, $productKey, 'lazy_create');
    }

    /**
     * @return Collection<int, ProductActivation>
     */
    public function forUser(User $user): Collection
    {
        return ProductActivation::query()
            ->where('user_id', $user->id)
            ->orderBy('product_key')
            ->get();
    }

    /**
     * @return Collection<int, ProductActivation>
     */
    public function forProduct(string $productKey, ?ProductActivationState $state = null): Collection
    {
        $this->assertKnown($productKey);

        $query = ProductActivation::query()->where('product_key', $productKey);

        if ($state !== null) {
            $query->where('state', $state->value);
        }

        return $query->orderBy('updated_at', 'desc')->get();
    }

    public function countByProductState(string $productKey, ProductActivationState $state): int
    {
        $this->assertKnown($productKey);

        return ProductActivation::query()
            ->where('product_key', $productKey)
            ->where('state', $state->value)
            ->count();
    }

    private function transition(
        ProductActivation $activation,
        ProductActivationState $target,
        string $source,
        ?array $payload = null,
    ): ProductActivation {
        $previous = $activation->state->value;

        $activation->state = $target;
        $activation->source = $source;

        if ($payload !== null) {
            $activation->provisioning_payload = $payload;
        }

        $activation->save();

        ProductActivationStateChanged::dispatch($activation, $previous);
        $this->realtime->activationChanged(
            $activation->product_key,
            $target->value,
            $activation->user_id,
        );

        return $activation;
    }

    private function assertKnown(string $productKey): void
    {
        if (! $this->registry::has($productKey)) {
            throw new ProductNotFoundException(__('product-activations.unknown_product', ['product' => $productKey]));
        }
    }

    private function assertType(string $productKey, ProductActivationType $expected): void
    {
        $actual = $this->registry::activationType($productKey);

        if ($actual !== $expected) {
            throw new ProductActivationException(__('product-activations.type_mismatch', [
                'product' => $productKey,
                'expected' => $expected->value,
                'actual' => $actual->value,
            ]));
        }
    }
}
