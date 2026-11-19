<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the `api` user from a bearer token when present, but never rejects
 * the request. Endpoints that serve both guests and authenticated users (e.g.
 * the navigation tree) use this so a missing or invalid token simply means
 * "treat as guest" instead of returning 401.
 */
class OptionalJwtAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! JWTAuth::parser()->setRequest($request)->hasToken()) {
            return $next($request);
        }

        try {
            $user = JWTAuth::parseToken()->authenticate();

            if ($user) {
                Auth::guard('api')->setUser($user);
            }
        } catch (JWTException) {
            // Invalid/expired/blacklisted token: continue as guest.
        }

        return $next($request);
    }
}
