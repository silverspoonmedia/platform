<?php

/**
 * Kernel defaults for the third-party services the kernel talks to.
 *
 * A consumer that installs `silverspoonmedia/platform` alone has no
 * `config/services.php`, so every one of these has to have a usable default
 * here — above all `discord.redirect`, which derives from `app.url` and would
 * otherwise be `null` at the OAuth callback. The host keeps its own copy with
 * the same keys; `mergeConfigFrom` is shallow, so the host wins.
 */
return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'discord' => [
        'client_id' => env('DISCORD_CLIENT_ID'),
        'client_secret' => env('DISCORD_CLIENT_SECRET'),
        'redirect' => env('DISCORD_REDIRECT_URI') ?: rtrim((string) config('app.url'), '/').'/apps/auth/discord/callback',
        // Tier B — Settings → Identity (identity.discord.connections_snapshot_max_age_minutes)
        'connections_snapshot_max_age_minutes' => 60,
    ],

    /*
    | Vue-Workers SPA public origin. OAuth return_to allowlist (with APP_URL),
    | password-reset / email-verify mail links, public share URLs,
    | and iPaymu hosted return/cancel defaults. Empty keeps signed-API URLs.
    */
    'spa' => [
        'url' => env('SPA_URL', ''),
    ],

];
