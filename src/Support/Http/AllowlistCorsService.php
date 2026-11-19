<?php

namespace App\Support\Http;

use Fruitcake\Cors\CorsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CORS service that refuses to answer a request whose `Origin` is not on the
 * allowlist.
 *
 * `fruitcake/php-cors` short-circuits when the allowlist holds exactly one
 * origin: `configureAllowedOrigin()` writes that value without ever comparing
 * it to the request's `Origin` header. With `supports_credentials` on, a
 * request from `https://evil.example` therefore leaves with
 * `Access-Control-Allow-Origin: <the one allowed origin>` plus
 * `Access-Control-Allow-Credentials: true`, and the shortcut skips
 * `Vary: Origin` on the way out. The browser blocks the read — the header does
 * not match the request origin — so this is not exploitable, but the allowlist
 * is supposed to be the thing that decides, and the header should not be there.
 *
 * A single-origin allowlist is a normal deployment (`SPA_URL` unset, only
 * `APP_URL` configured), so the shortcut is reachable in production and not
 * only in tests. `tests/Feature/Http/CorsOriginsTest.php` is the regression
 * test: it asserts a foreign origin receives no allow-origin header.
 *
 * Both writers are overridden — the actual response and the preflight response.
 * The parameter type stays `Symfony\Component\HttpFoundation\Request` to match
 * the parent signature; Laravel passes its own `Illuminate\Http\Request`, which
 * extends it.
 */
final class AllowlistCorsService extends CorsService
{
    public function addActualRequestHeaders(Response $response, Request $request): Response
    {
        return $this->enforceAllowlist(
            parent::addActualRequestHeaders($response, $request),
            $request,
        );
    }

    public function addPreflightRequestHeaders(Response $response, Request $request): Response
    {
        return $this->enforceAllowlist(
            parent::addPreflightRequestHeaders($response, $request),
            $request,
        );
    }

    /**
     * Strip the allow-origin grant the single-origin shortcut handed out
     * without checking the request, and restore the `Vary: Origin` the
     * dynamic path would have set so a shared cache cannot replay the
     * decision to another origin.
     */
    private function enforceAllowlist(Response $response, Request $request): Response
    {
        if (! $response->headers->has('Access-Control-Allow-Origin')) {
            return $response;
        }

        if ($this->isOriginAllowed($request)) {
            return $response;
        }

        $response->headers->remove('Access-Control-Allow-Origin');
        $response->headers->remove('Access-Control-Allow-Credentials');
        $response->headers->remove('Access-Control-Expose-Headers');

        return $this->varyHeader($response, 'Origin');
    }
}
