<?php

return [
    'oauth' => [
        'discord_not_configured' => 'Discord OAuth is not configured on this server.',
        'token_exchange_failed' => 'Discord authorization failed. Please try again.',
        'token_refresh_failed' => 'Discord session expired. Reconnect Discord from Connected accounts.',
        'connections_failed' => 'Could not load Discord linked accounts.',
        'discord_not_connected' => 'Connect Discord from Connected accounts first.',
        'discord_user_missing' => 'Discord did not return a user id.',
        'authorize_url' => 'Discord authorization URL issued.',
        'spa_url_missing' => 'SPA origin is not configured on this server.',
        'return_path_invalid' => 'OAuth return path is not allowed.',
    ],
    'connections' => [
        'show' => 'Connected accounts loaded.',
        'synced' => 'Linked accounts updated.',
        'disconnected' => 'Account disconnected.',
        'discord_disconnected' => 'Discord disconnected.',
    ],
];
