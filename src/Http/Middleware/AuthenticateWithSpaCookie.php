<?php

namespace App\Http\Middleware;

use App\Support\Auth\SpaAuthCookie;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the SPA's cross-site HttpOnly JWT cookie to the `api` guard.
 *
 * The Vue-Workers SPA calls the Laravel API directly (no Worker in the data
 * path) with `credentials: 'include'`; the browser attaches the
 * {@see SpaAuthCookie::NAME} cookie. This middleware reads it and binds it to
 * the `api` guard so the existing `auth:api` routes, Spatie RBAC, and policies
 * work unchanged for direct SPA traffic.
 *
 * It never rejects: a missing/invalid/expired token leaves the guard as guest.
 * Route-level `auth:api` decides what to do. When the SPA instead sends an
 * `Authorization: Bearer` header (Fase 2 fallback), the package guard already
 * handles it and this middleware is a no-op.
 */
class AuthenticateWithSpaCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        // Bearer already present (Fase 2 bearer-memory mode, or machine client):
        // let the package guard authenticate it; do not clobber with the cookie.
        if ($request->bearerToken() !== null) {
            return $next($request);
        }

        $token = $request->cookie(SpaAuthCookie::NAME);

        if (is_string($token) && $token !== '') {
            /** @var JWTGuard $guard */
            $guard = Auth::guard('api');

            try {
                $guard->setToken($token)->user();
            } catch (JWTException) {
                // Invalid/expired/blacklisted token: continue as a guest.
            }
        }

        return $next($request);
    }
}
