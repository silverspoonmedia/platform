<?php

namespace App\Support\Auth;

use Symfony\Component\HttpFoundation\Cookie;

/**
 * HttpOnly cookie that carries the stateless JWT for the Vue-Workers SPA.
 *
 * Unlike the removed Blade first-party JWT cookie (`lax` on APP_URL), this cookie is
 * minted on the API origin and consumed cross-site by the SPA via
 * `fetch(..., { credentials: 'include' })`. It therefore needs `SameSite=None;
 * Secure` so browsers attach it to cross-origin requests, and the `__Host-`
 * prefix so only this host could have set it (no Domain attribute, Path=/).
 *
 * JavaScript never reads it: `httpOnly` stays true. The SPA authenticates data
 * requests purely by the browser attaching this cookie, keeping the
 * token-never-touches-JS guarantee while removing the Worker from the hot data
 * path.
 */
class SpaAuthCookie
{
    public const NAME = '__Host-spa_jwt';

    /**
     * Build the cookie that persists a freshly issued JWT.
     *
     * @param  int  $ttlSeconds  Lifetime in seconds (mirrors the token's `expires_in`).
     */
    public static function make(string $token, int $ttlSeconds): Cookie
    {
        return cookie()->make(
            name: self::NAME,
            value: $token,
            minutes: (int) ceil($ttlSeconds / 60),
            path: '/',
            // __Host- forbids a Domain attribute; host-only cookie.
            domain: null,
            // SameSite=None is only honored with Secure.
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'none',
        );
    }

    /**
     * Build the cookie that clears the stored JWT on logout.
     */
    public static function forget(): Cookie
    {
        return cookie()->make(
            name: self::NAME,
            value: '',
            minutes: -1,
            path: '/',
            domain: null,
            secure: true,
            httpOnly: true,
            raw: false,
            sameSite: 'none',
        );
    }
}
