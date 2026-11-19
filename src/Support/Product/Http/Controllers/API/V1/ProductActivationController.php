<?php

namespace App\Support\Product\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\Product\Http\Resources\ProductActivationResource;
use App\Support\Product\Services\ProductActivationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductActivationController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(private readonly ProductActivationService $activations) {}

    public function index(Request $request): JsonResponse
    {
        $activations = $this->activations->forUser($request->user());

        return $this->successResponse(
            $this->resolveResource(ProductActivationResource::collection($activations)),
            __('product-activations.list_success'),
        );
    }

    public function show(Request $request, string $productKey): JsonResponse
    {
        $activation = $this->activations->findOrFail($request->user()->id, $productKey);

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($activation)),
            __('product-activations.show_success'),
        );
    }
}
