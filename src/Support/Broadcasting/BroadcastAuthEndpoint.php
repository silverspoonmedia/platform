<?php

namespace App\Support\Broadcasting;

/**
 * Single source of truth for the browser-facing broadcast auth endpoint.
 *
 * Laravel's `withRouting(channels: ...)` registers a second
 * `POST /broadcasting/auth` under the `web` group (session + CSRF). That route
 * is not CORS-covered — `config/cors.php` `paths` lists API paths — and answers
 * 403 without any `Access-Control-*` header, so any client that resolves the
 * auth URL without the `/api/v1` prefix sees an opaque browser CORS error
 * instead of the real status.
 *
 * The route definition and every server-issued client config (Pusher/Echo
 * `authEndpoint`) both go through this class so the two can never drift.
 */
final class BroadcastAuthEndpoint
{
    /** Version prefix of the API surface (`bootstrap/app.php`). */
    public const API_PREFIX = 'api/v1';

    /** Path relative to the API prefix (used inside the route group). */
    public const SUBPATH = 'broadcasting/auth';

    /** Absolute app path, e.g. `api/v1/broadcasting/auth`. */
    public const PATH = self::API_PREFIX.'/'.self::SUBPATH;

    /** Absolute URL clients should POST to. */
    public static function url(): string
    {
        return url(self::PATH);
    }
}
