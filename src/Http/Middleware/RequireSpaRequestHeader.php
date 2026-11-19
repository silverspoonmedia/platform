<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Requires the public SPA request marker on browser-facing API calls.
 *
 * The marker is an anti-noise signal, not authentication: clients can inspect
 * and replay it. JWT, RBAC, CSRF, throttles, and provider-specific controls
 * remain the security boundaries. Provider callbacks stay reachable so their
 * own signature and payload validation can decide whether to accept them.
 */
class RequireSpaRequestHeader
{
    public const HEADER = 'X-SPA-Origin';

    public const VALUE = '1';

    /** @var list<string> */
    private const EXEMPT_PATHS = [
        'api/v1/payments/providers/midtrans/webhook',
        'api/v1/payments/providers/ipaymu/webhook',
    ];

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (! $request->is('api/v1/*') && $request->path() !== 'api/v1') {
            return $next($request);
        }

        if (
            $request->isMethod('OPTIONS')
            || in_array($request->path(), self::EXEMPT_PATHS, true)
            || $request->header(self::HEADER) === self::VALUE
        ) {
            return $next($request);
        }

        return ApiResponder::error(
            __('api/common.spa_origin_required'),
            SymfonyResponse::HTTP_FORBIDDEN,
        );
    }
}
