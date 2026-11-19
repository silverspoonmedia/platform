<?php

namespace App\Support\Product\Http\Middleware;

use App\Support\ApiResponder;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate product domain routes. Resolves product_key from the middleware
 * argument (e.g. `product.active:Archivd`). Allows through when an
 * Active activation exists; lazy-creates implicit activations on first
 * miss for backward-compat; rejects explicit + non-active with 403.
 */
class ProductActive
{
    public function __construct(
        private readonly ProductActivationService $activations,
        private readonly ProductRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next, string $productKey): Response
    {
        $user = $request->user('api');

        if ($user === null) {
            return ApiResponder::error(__('product-activations.auth_required'), 401);
        }

        if (! $this->registry::has($productKey)) {
            throw new ProductNotFoundException(__('product-activations.unknown_product', ['product' => $productKey]));
        }

        $activation = $this->activations->find($user->id, $productKey);

        if ($activation === null) {
            if ($this->registry::isImplicit($productKey)) {
                $this->activations->ensureImplicitActive($user, $productKey);

                return $next($request);
            }

            return $this->deny(__('product-activations.not_provisioned', ['product' => $productKey]));
        }

        if ($activation->state === ProductActivationState::Active) {
            return $next($request);
        }

        return $this->deny(__('product-activations.inactive', [
            'product' => $productKey,
            'state' => $activation->state->value,
        ]));
    }

    private function deny(string $message): Response
    {
        return ApiResponder::error($message, Response::HTTP_FORBIDDEN);
    }
}
