<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamp CORS allow-origin on API responses that left the kernel without
 * passing through HandleCors (exception renderer, abort()).
 *
 * Credentialed SPA calls hide the real status as a generic CORS error when
 * Access-Control-Allow-Origin is missing, so 401/403/419/500 must still match
 * the same allowlist as config/cors.php.
 */
final class StampApiCors
{
    public static function apply(Response $response, Request $request): Response
    {
        if (! $request->is('api/*')) {
            return $response;
        }

        if ($response->headers->has('Access-Control-Allow-Origin')) {
            return $response;
        }

        $origin = $request->headers->get('Origin');
        if (! is_string($origin) || $origin === '') {
            return $response;
        }

        $allowed = config('cors.allowed_origins', []);
        if (! is_array($allowed) || ! in_array($origin, $allowed, true)) {
            return $response;
        }

        $response->headers->set('Access-Control-Allow-Origin', $origin);

        if (config('cors.supports_credentials')) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        $vary = $response->headers->get('Vary');
        if (! is_string($vary) || $vary === '') {
            $response->headers->set('Vary', 'Origin');
        } elseif (! str_contains(strtolower($vary), 'origin')) {
            $response->headers->set('Vary', $vary.', Origin');
        }

        return $response;
    }
}
