<?php

namespace App\Support\Localization;

use Illuminate\Support\Facades\Cookie;

final class LocaleCookie
{
    public static function name(): string
    {
        return (string) setting_config('localization.cookie_name', 'locale');
    }

    public static function queue(string $locale): void
    {
        Cookie::queue(Cookie::make(
            self::name(),
            $locale,
            setting_config('localization.cookie_minutes', 60 * 24 * 365),
            '/',
            null,
            config('session.secure'),
            false,
            false,
            config('session.same_site'),
        ));
    }
}
