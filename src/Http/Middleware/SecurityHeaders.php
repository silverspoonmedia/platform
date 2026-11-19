<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds security headers to HTTP responses.
 *
 * Protects against common web vulnerabilities:
 * - Clickjacking (X-Frame-Options)
 * - MIME sniffing (X-Content-Type-Options)
 * - Referrer leakage (Referrer-Policy)
 * - Unwanted browser features (Permissions-Policy)
 * - Script exfiltration (Content-Security-Policy)
 *
 * CSP applies to the surviving server-side `web` routes this middleware runs
 * on (OAuth callbacks, payment returns, public share); the Vue-Workers SPA
 * sets its own policy at the edge. hCaptcha is whitelisted for server-rendered
 * challenge forms.
 */
class SecurityHeaders
{
    private const CSP_DIRECTIVES = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' https://hcaptcha.com https://*.hcaptcha.com",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: https:",
        "font-src 'self' data:",
        "connect-src 'self' https://*.pusher.com wss://*.pusher.com https://hcaptcha.com https://*.hcaptcha.com",
        'frame-src https://hcaptcha.com https://*.hcaptcha.com',
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent page from being framed (clickjacking protection)
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevent MIME sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Control referrer information
        $response->headers->set('Referrer-Policy', 'no-referrer-when-downgrade');

        // Disable potentially dangerous browser features
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=(), magnetometer=(), gyroscope=()'
        );

        // Local Vite dev injects HMR/eval scripts a strict CSP would break, so
        // the policy only ships from non-local environments.
        if (! app()->environment('local')) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::CSP_DIRECTIVES));
        }

        return $response;
    }
}
