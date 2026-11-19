<?php

namespace App\Support\Localization;

use App\Domain\Identity\Models\User;

/**
 * Shared locale cookie + optional user-column persist for Web and API paths.
 */
final class LocalePreference
{
    private const SPA_TO_BACKEND = [
        'zh-CN' => 'zh_CN',
    ];

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        $supported = config('localization.supported_locales', ['en']);

        if (! is_array($supported)) {
            return ['en'];
        }

        $supported = array_values(array_filter($supported, 'is_string'));

        return $supported !== [] ? $supported : ['en'];
    }

    /**
     * Normalize SPA aliases to the canonical Laravel locale code.
     */
    public static function normalize(string $locale): ?string
    {
        $locale = trim($locale);
        $canonical = self::SPA_TO_BACKEND[$locale] ?? $locale;

        return self::isCanonicalSupported($canonical) ? $canonical : null;
    }

    /**
     * @return list<string>
     */
    public static function accepted(): array
    {
        $accepted = self::supported();

        foreach (self::SPA_TO_BACKEND as $spa => $backend) {
            if (in_array($backend, $accepted, true)) {
                $accepted[] = $spa;
            }
        }

        return array_values(array_unique($accepted));
    }

    public static function toSpa(string $locale): ?string
    {
        $canonical = self::normalize($locale);

        if ($canonical === null) {
            return null;
        }

        return array_search($canonical, self::SPA_TO_BACKEND, true) ?: $canonical;
    }

    public static function isSupported(string $locale): bool
    {
        return self::normalize($locale) !== null;
    }

    private static function isCanonicalSupported(string $locale): bool
    {
        return in_array($locale, self::supported(), true);
    }

    /**
     * Queue locale cookie and persist on the user when authenticated.
     */
    public static function apply(string $locale, ?User $user = null): void
    {
        LocaleCookie::queue($locale);

        if (! $user instanceof User) {
            return;
        }

        $column = config('localization.user_column');

        if (! is_string($column) || $column === '') {
            return;
        }

        if ($user->{$column} === $locale) {
            return;
        }

        $user->forceFill([$column => $locale])->save();
    }
}
