<?php

namespace App\Support\Settings;

use App\Support\Settings\Contracts\SettingContributor;

/**
 * Static facade over {@see SettingCatalog}.
 *
 * Kept so the ~10 call sites and the locale/env guards do not have to inject a
 * catalog. Delegation is container-aware: the host and a consumer bind the
 * catalog as a singleton, and a bare Testbench kernel that never bound it still
 * resolves the kernel-only defaults instead of failing.
 */
final class SettingRegistry
{
    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public static function definitions(): array
    {
        return self::catalog()->definitions();
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return self::catalog()->groups();
    }

    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public static function forGroup(string $group): array
    {
        return self::catalog()->forGroup($group);
    }

    /**
     * @return array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}|null
     */
    public static function get(string $key): ?array
    {
        return self::catalog()->get($key);
    }

    /**
     * @return array<string, string>
     */
    public static function configPathIndex(): array
    {
        return self::catalog()->configPathIndex();
    }

    /**
     * Register a contributor from a product provider.
     *
     * @param  class-string<SettingContributor>  ...$contributors
     */
    public static function register(string ...$contributors): void
    {
        self::catalog()->register(...$contributors);
    }

    private static function catalog(): SettingCatalog
    {
        if (function_exists('app') && app()->bound(SettingCatalog::class)) {
            return app(SettingCatalog::class);
        }

        static $fallback = null;

        return $fallback ??= new SettingCatalog;
    }
}
