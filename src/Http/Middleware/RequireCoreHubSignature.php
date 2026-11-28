<?php

namespace App\Http\Middleware;

use App\Support\ApiResponder;
use App\Support\CoreHub\Exceptions\InvalidHubSignature;
use App\Support\CoreHub\HubSignature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Verifies a push from a core hub before any controller sees it.
 *
 * The internal surface (`/internal/v1/*`) is not browser traffic: no SPA marker,
 * no JWT cookie, no CSRF nonce — a machine caller holds a shared secret instead.
 * The secret is looked up per consumer key from `core_hub.inbound.consumers`, so
 * a payload signed for one consumer is worthless to another, and the signature
 * covers method, path, timestamp and body hash (see `HubSignature`).
 *
 * Everything here runs before the route's handler; a failure is a bare 401 with
 * the same message for every cause, so a probe cannot tell a bad key from a
 * replayed one.
 */
class RequireCoreHubSignature
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $secrets = (array) config('core_hub.inbound.consumers', []);
        $consumer = (string) $request->header(HubSignature::CONSUMER_HEADER, '');
        $secret = is_string($secrets[$consumer] ?? null) ? $secrets[$consumer] : '';

        $timestamp = (int) $request->header(HubSignature::TIMESTAMP_HEADER, '0');
        $signature = (string) $request->header(HubSignature::HEADER, '');

        try {
            if ($secret === '') {
                throw new InvalidHubSignature('unknown_consumer');
            }

            $valid = HubSignature::verify(
                $secret,
                $timestamp,
                $request->getMethod(),
                $request->getPathInfo(),
                (string) $request->getContent(),
                $signature,
            );

            if (! $valid) {
                throw new InvalidHubSignature();
            }
        } catch (InvalidHubSignature $e) {
            return ApiResponder::error($e->getMessage(), SymfonyResponse::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
