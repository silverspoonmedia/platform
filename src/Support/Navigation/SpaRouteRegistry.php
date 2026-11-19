<?php

namespace App\Support\Navigation;

use Illuminate\Support\Facades\Route;

/**
 * Validates menu `route` column values after Blade removal.
 *
 * Values are SPA Vue Router symbolic names (e.g. `dashboard`, `users.index`).
 * Legacy Laravel web route names still register here when they remain on the
 * `web` stack (public share, payment return, OAuth callback) unless denied.
 */
final class SpaRouteRegistry
{
    /**
     * @return list<string>
     */
    public static function menuRouteNames(): array
    {
        /** @var list<string> $names */
        $names = config('spa-routes.menu', []);

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function deniedRouteNames(): array
    {
        /** @var list<string> $names */
        $names = config('spa-routes.deny', []);

        return $names;
    }

    public static function isValidMenuRoute(?string $name): bool
    {
        if ($name === null || $name === '') {
            return true;
        }

        if (in_array($name, self::deniedRouteNames(), true)) {
            return false;
        }

        if (Route::has($name)) {
            return true;
        }

        return in_array($name, self::menuRouteNames(), true);
    }
}
