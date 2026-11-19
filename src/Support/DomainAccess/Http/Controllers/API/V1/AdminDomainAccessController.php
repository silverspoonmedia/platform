<?php

namespace App\Support\DomainAccess\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Support\Domain\DomainManager;
use App\Support\DomainAccess\DomainAccessService;
use App\Support\DomainAccess\Http\Requests\UpdateDomainVisibilityRequest;
use App\Support\DomainAccess\ProductNames;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminDomainAccessController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly DomainAccessService $access,
        private readonly DomainManager $domains,
    ) {}

    /**
     * List every product domain with its current visibility + beta role name.
     */
    public function index(): JsonResponse
    {
        $rows = collect($this->domains->all())
            ->filter(fn (array $meta): bool => ($meta['kind'] ?? null) === 'product')
            ->keys()
            ->map(fn (string $key): array => $this->present($key))
            ->values()
            ->all();

        return $this->successResponse($rows, __('domain-access.list_success'));
    }

    /**
     * Set a product domain's visibility (public|private).
     */
    public function update(UpdateDomainVisibilityRequest $request, string $productKey): JsonResponse
    {
        if (! $this->domains->isRegistered($productKey) || ! $this->domains->isProduct($productKey)) {
            return $this->errorResponse(
                __('domain-access.unknown_product', ['product' => $productKey]),
                404,
            );
        }

        $this->access->setVisibility($productKey, $request->visibility());

        return $this->successResponse($this->present($productKey), __('domain-access.updated'));
    }

    /**
     * @return array{key: string, label: string, visibility: string, beta_role: string}
     */
    private function present(string $productKey): array
    {
        return [
            'key' => $productKey,
            'label' => ProductNames::label($productKey),
            'visibility' => $this->access->visibilityFor($productKey)->value,
            'beta_role' => ProductNames::betaRole($productKey),
        ];
    }
}
