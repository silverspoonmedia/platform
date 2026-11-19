<?php

namespace App\Http\Middleware;

use App\Support\Localization\LocaleCookie;
use App\Support\Localization\LocalePreference;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active application locale for the current request.
 *
 * Priority:
 * 1. Authenticated user's DB preference (users.locale)
 * 2. Explicit SPA locale hint (X-SPA-Locale header — always sent by the SPA)
 * 3. Locale cookie (guests and non-SPA requests)
 * 4. config('app.locale')
 *
 * The header sits above the cookie because the SPA always sends it on every
 * request and it reflects the current choice, whereas the cookie can be stale
 * (e.g. a direction-swap reload aborts the auth/locale persist that would
 * refresh the cookie). The authenticated DB preference stays authoritative.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $supported = LocalePreference::supported();
        $default = (string) config('app.locale', 'en');

        $userColumn = config('localization.user_column');

        if (is_string($userColumn) && $userColumn !== '') {
            $user = Auth::guard('api')->user();

            if ($user !== null) {
                $userLocale = $user->{$userColumn} ?? null;

                if (is_string($userLocale)) {
                    $normalized = LocalePreference::normalize($userLocale);
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }
        }

        $spaLocale = $request->header('X-SPA-Locale');
        if (is_string($spaLocale)) {
            $normalized = LocalePreference::normalize($spaLocale);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $cookieLocale = $request->cookie(LocaleCookie::name());

        if (is_string($cookieLocale)) {
            $normalized = LocalePreference::normalize($cookieLocale);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $normalizedDefault = LocalePreference::normalize($default);
        if ($normalizedDefault !== null && in_array($normalizedDefault, $supported, true)) {
            return $normalizedDefault;
        }

        return $supported[0] ?? 'en';
    }
}
