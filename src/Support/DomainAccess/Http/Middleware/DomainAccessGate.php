<?php

namespace App\Support\DomainAccess\Http\Middleware;

use App\Support\ApiResponder;
use App\Support\Domain\DomainManager;
use App\Support\DomainAccess\DomainAccessService;
use App\Support\Product\Exceptions\ProductNotFoundException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a product domain route group by its Public/Private visibility.
 *
 * Usage: `domain.access:Archivd`. Public domains pass for everyone;
 * Private domains pass only for Root/Admin/beta testers (see
 * DomainAccessService::canAccess). Denials render as JSON 403 for API
 * requests and abort(403) for web requests.
 */
class DomainAccessGate
{
    public function __construct(
        private readonly DomainAccessService $access,
        private readonly DomainManager $domains,
    ) {}

    public function handle(Request $request, Closure $next, string $productKey): Response
    {
        if (! $this->domains->isRegistered($productKey) || ! $this->domains->isProduct($productKey)) {
            throw new ProductNotFoundException(
                __('domain-access.unknown_product', ['product' => $productKey])
            );
        }

        $user = $request->user('api') ?? $request->user();

        if ($this->access->canAccess($user, $productKey)) {
            return $next($request);
        }

        if ($user === null) {
            return $this->deny($request, __('domain-access.auth_required'), Response::HTTP_UNAUTHORIZED);
        }

        return $this->deny($request, __('domain-access.forbidden'), Response::HTTP_FORBIDDEN);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponder::error($message, $status);
        }

        abort($status, $message);
    }
}
