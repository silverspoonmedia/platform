<?php

namespace App\Support\Localization;

final class TranslationResolver
{
    /**
     * Resolve a Spatie-style translations map for the active (or given) locale.
     *
     * @param  array<string, string>  $translations
     */
    public static function resolve(array $translations, ?string $locale = null): string
    {
        if ($translations === []) {
            return '';
        }

        $locale ??= app()->getLocale();
        $fallback = (string) config('app.fallback_locale', 'en');

        if (isset($translations[$locale]) && trim((string) $translations[$locale]) !== '') {
            return (string) $translations[$locale];
        }

        if (self::requiresStrictLocale($locale)) {
            return '';
        }

        if (isset($translations[$fallback]) && trim((string) $translations[$fallback]) !== '') {
            return (string) $translations[$fallback];
        }

        foreach ($translations as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function requiresStrictLocale(string $locale): bool
    {
        return $locale !== (string) config('app.fallback_locale', 'en')
            && in_array($locale, LocalePreference::supported(), true);
    }
}
