<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported locales
    |--------------------------------------------------------------------------
    |
    | Only locales listed here may be resolved by SetLocale middleware or
    | stored on the users.locale column. Keep in sync with lang/ directories.
    |
    */

    'supported_locales' => ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru'],

    /*
    |--------------------------------------------------------------------------
    | Locale cookie
    |--------------------------------------------------------------------------
    |
    | Guests (and logged-in users without a DB preference) fall back to this
    | cookie before config('app.locale').
    |
    */

    'cookie_name' => 'locale',

    'cookie_minutes' => 60 * 24 * 365,

    /*
    |--------------------------------------------------------------------------
    | User column
    |--------------------------------------------------------------------------
    |
    | Column on users that stores the preferred locale when authenticated.
    | Set to null to disable DB preference (cookie + app default only).
    |
    */

    'user_column' => 'locale',

    /*
    |--------------------------------------------------------------------------
    | Package-managed translation files (laravel-lang)
    |--------------------------------------------------------------------------
    |
    | Files touched by `php artisan lang:update`. Never store application-
    | owned strings in these files — they may be overwritten on package update.
    |
    */

    'managed_files' => [
        'actions.php',
        'auth.php',
        'http-statuses.php',
        'pagination.php',
        'passwords.php',
        'validation.php',
        '*.json',
    ],

];
