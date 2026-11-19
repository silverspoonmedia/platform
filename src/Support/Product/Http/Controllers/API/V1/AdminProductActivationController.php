<?php

namespace App\Support\Product\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Concerns\ResolvesResourceArray;
use App\Support\Product\Enums\ProductActivationState;
use App\Support\Product\Http\Requests\ApproveProductActivationRequest;
use App\Support\Product\Http\Requests\IndexProductActivationRequest;
use App\Support\Product\Http\Requests\RejectProductActivationRequest;
use App\Support\Product\Http\Requests\ShowProductActivationRequest;
use App\Support\Product\Http\Requests\StatsProductActivationRequest;
use App\Support\Product\Http\Requests\SuspendProductActivationRequest;
use App\Support\Product\Http\Resources\ProductActivationResource;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use App\Support\Product\Services\ProductInsightService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminProductActivationController extends Controller
{
    use ApiResponse;
    use ResolvesResourceArray;

    public function __construct(
        private readonly ProductActivationService $activations,
        private readonly ProductInsightService $insights,
    ) {}

    /**
     * List all registered products with their per-product summary (counts +
     * product-specific metrics). Drives the product-cards index UI.
     */
    public function products(): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $products = collect(ProductRegistry::all())
            ->map(fn (array $config, string $key): array => $this->insights->productSummary($key))
            ->values();

        return $this->successResponse(
            $products->all(),
            __('product-activations.products_list_success'),
        );
    }

    /**
     * Full per-product dashboard: summary + product metrics + paginated
     * activations with filter by state.
     */
    public function productShow(ShowProductActivationRequest $request, string $productKey): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        if (! ProductRegistry::has($productKey)) {
            return $this->errorResponse(
                __('product-activations.unknown_product', ['product' => $productKey]),
                404,
            );
        }

        $validated = $request->validated();

        $query = ProductActivation::query()
            ->with('user')
            ->where('product_key', $productKey);

        if (isset($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        $activations = $query->orderBy('updated_at', 'desc')->paginate(50);

        return $this->successResponse(
            [
                'summary' => $this->insights->productSummary($productKey),
                'activations' => $this->resolveResource(ProductActivationResource::collection($activations->items())),
                'pagination' => [
                    'current_page' => $activations->currentPage(),
                    'last_page' => $activations->lastPage(),
                    'per_page' => $activations->perPage(),
                    'total' => $activations->total(),
                ],
            ],
            __('product-activations.product_show_success'),
        );
    }

    /**
     * Per-user drill-down inside a product.
     */
    public function userDetail(string $productKey, string $userId): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        if (! ProductRegistry::has($productKey)) {
            return $this->errorResponse(
                __('product-activations.unknown_product', ['product' => $productKey]),
                404,
            );
        }

        return $this->successResponse(
            $this->insights->userDetailInProduct($userId, $productKey),
            __('product-activations.user_detail_success'),
        );
    }

    public function index(IndexProductActivationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $validated = $request->validated();

        $query = ProductActivation::query()->with('user');

        if (isset($validated['product_key'])) {
            $query->where('product_key', $validated['product_key']);
        }

        if (isset($validated['state'])) {
            $query->where('state', $validated['state']);
        }

        if (isset($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        $activations = $query->orderBy('updated_at', 'desc')->paginate(50);

        return $this->successResponse(
            $this->resolveResource(ProductActivationResource::collection($activations->items())),
            __('product-activations.admin_list_success'),
        );
    }

    public function show(ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('view', $productActivation);

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($productActivation->load('user'))),
            __('product-activations.show_success'),
        );
    }

    public function suspend(SuspendProductActivationRequest $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $updated = $this->activations->suspend(
            $productActivation,
            $request->validated('reason'),
            'admin',
        );

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($updated)),
            __('product-activations.suspended'),
        );
    }

    public function reactivate(ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $updated = $this->activations->reactivate($productActivation, 'admin');

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($updated)),
            __('product-activations.reactivated'),
        );
    }

    public function approve(ApproveProductActivationRequest $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $updated = $this->activations->approve(
            $productActivation,
            $request->validated('payload') ?? [],
            'admin',
        );

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($updated)),
            __('product-activations.approved'),
        );
    }

    public function reject(RejectProductActivationRequest $request, ProductActivation $productActivation): JsonResponse
    {
        $this->authorize('update', $productActivation);

        $updated = $this->activations->reject($productActivation, $request->validated('reason'), 'admin');

        return $this->successResponse(
            $this->resolveResource(new ProductActivationResource($updated)),
            __('product-activations.rejected'),
        );
    }

    public function stats(StatsProductActivationRequest $request): JsonResponse
    {
        $this->authorize('viewAny', ProductActivation::class);

        $productKey = $request->validated('product_key');

        $stats = collect(ProductActivationState::cases())->map(function (ProductActivationState $state) use ($productKey): array {
            $query = ProductActivation::query()->where('state', $state->value);

            if ($productKey !== null) {
                $query->where('product_key', $productKey);
            }

            return [
                'state' => $state->value,
                'count' => $query->count(),
            ];
        });

        return $this->successResponse(
            $stats->all(),
            __('product-activations.stats_success'),
        );
    }
}
