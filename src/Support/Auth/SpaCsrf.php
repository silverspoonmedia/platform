<?php

namespace App\Support\Auth;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Double-submit CSRF token for the SPA's cross-site cookie auth.
 *
 * Because the SPA authenticates data requests with the cross-site
 * {@see SpaAuthCookie} (SameSite=None), a malicious third-party page could ride
 * that cookie with a forged cross-origin form/fetch. The classic SPA meta-tag
 * approach is unavailable across origins, so the API mints a random nonce and
 * sets it on a NON-HttpOnly cookie (`__Host-spa_csrf`). The SPA reads that cookie and
 * echoes the value back in the `X-CSRF-Token` header; {@see
 * \App\Http\Middleware\VerifySpaCsrf} accepts the mutation only when header and
 * cookie match.
 *
 * A cross-origin attacker cannot read the cookie (HttpOnly not required, but
 * the SameSite=None cookie is still scoped to the API host, so the attacker's
 * origin never receives it) and therefore cannot guess the header value.
 */
class SpaCsrf
{
    public const COOKIE_NAME = '__Host-spa_csrf';

    public const HEADER = 'X-CSRF-Token';

    /**
     * Generate a fresh nonce.
     */
    public static function token(): string
    {
        return Str::random(64);
    }

    /**
     * Non-HttpOnly cookie carrying the nonce, readable by the SPA's JS.
     */
    public static function makeCookie(string $token): Cookie
    {
        return cookie()->make(
            name: self::COOKIE_NAME,
            value: $token,
            // Session cookie: lives until the browser closes.
            minutes: 0,
            path: '/',
            domain: null,
            secure: true,
            // JS must read it, so NOT HttpOnly.
            httpOnly: false,
            raw: false,
            sameSite: 'none',
        );
    }
}
