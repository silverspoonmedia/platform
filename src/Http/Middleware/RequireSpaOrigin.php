<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reads the optional `X-SPA-Origin` observability header and audits requests
 * that do not carry it.
 *
 * Audit-only. Direct browser-to-API traffic is the sanctioned data path (the
 * Cloudflare Worker serves static assets only and brokers neither auth nor
 * data), so a missing header is not a violation — it is simply logged for
 * observability. The header is trivially spoofable and was never an auth
 * control; real protection lives in JWT auth, RBAC, throttling, and the WAF.
 * OPTIONS preflights always pass.
 */
class RequireSpaOrigin
{
    public const ATTRIBUTE = 'spa_origin';

    public function handle(Request $request, Closure $next): Response
    {
        $hasOriginStamp = $request->headers->get('X-SPA-Origin') === '1';

        $request->attributes->set(self::ATTRIBUTE, $hasOriginStamp);

        if (! $hasOriginStamp && $request->method() !== 'OPTIONS') {
            Log::channel('spa_audit')->info('api.direct', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->getAuthIdentifier(),
            ]);
        }

        return $next($request);
    }
}
