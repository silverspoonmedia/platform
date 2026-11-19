<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use App\Support\Auth\SpaAuthCookie;
use App\Support\Auth\SpaCsrf;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the double-submit CSRF token for cookie-authenticated SPA mutations.
 *
 * Only applies when the request is (a) a mutating method and (b) carries the
 * cross-site auth cookie — exactly the case vulnerable to CSRF. Requests using
 * an `Authorization: Bearer` header are not cookie-driven and are skipped, as
 * are safe methods (GET/HEAD/OPTIONS).
 */
class VerifySpaCsrf
{
    /** @var list<string> */
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * Auth-session endpoints establish (or end) the session itself, so a valid
     * CSRF nonce cannot be required beforehand: a guest has no nonce yet, and a
     * client whose session went stale must still be able to re-login or refresh.
     * They mutate no sensitive state beyond the session and are throttled, so
     * skipping CSRF here is the standard pattern.
     *
     * @var list<string>
     */
    private const AUTH_SESSION_PATHS = [
        'api/v1/auth/login',
        'api/v1/auth/register',
        'api/v1/auth/refresh',
        'api/v1/auth/logout',
        'api/v1/auth/forgot-password',
        'api/v1/auth/reset-password',
        'api/v1/auth/two-factor/login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), self::SAFE_METHODS, true)) {
            return $next($request);
        }

        // Bearer-auth (non-cookie) requests are not CSRF-able.
        if ($request->bearerToken() !== null) {
            return $next($request);
        }

        // Session-establishing endpoints: no pre-existing nonce to verify.
        if ($this->isAuthSessionPath($request)) {
            return $next($request);
        }

        // No auth cookie means an unauthenticated mutation; auth:api rejects it
        // downstream, so there is no cookie session to protect here.
        $authCookie = $request->cookie(SpaAuthCookie::NAME);

        if (! is_string($authCookie) || $authCookie === '') {
            return $next($request);
        }

        $cookieToken = $request->cookie(SpaCsrf::COOKIE_NAME);
        $headerToken = $request->headers->get(SpaCsrf::HEADER);

        if (
            ! is_string($cookieToken) || $cookieToken === ''
            || ! is_string($headerToken) || $headerToken === ''
            || ! hash_equals($cookieToken, $headerToken)
        ) {
            // Return a response (do not abort) so HandleCors can still stamp
            // Access-Control-Allow-* and the SPA can read the 419 and retry.
            return ApiResponder::error(__('api/common.spa_csrf_mismatch'), 419);
        }

        return $next($request);
    }

    private function isAuthSessionPath(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        foreach (self::AUTH_SESSION_PATHS as $allowed) {
            if ($path === $allowed) {
                return true;
            }
        }

        return false;
    }
}
