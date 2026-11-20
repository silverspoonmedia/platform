<?php

/*
|--------------------------------------------------------------------------
| SPA menu route names
|--------------------------------------------------------------------------
|
| Symbolic Vue Router route names stored in navigation menus. These are NOT
| registered Laravel routes after Blade removal; the SPA resolves them
| client-side. Keep in sync with monolith-vue-workers/src/router/index.ts
| (parent names that do not end in `.view`).
|
| `deny` wins over both the allowlist and Laravel Route::has() so auth /
| public-utility names cannot be stored as sidebar targets.
|
*/

return [
    'deny' => [
        'login',
        'register',
        'forgot-password',
        'reset-password',
        'verify-email',
        'two-factor.challenge',
        'not-found',
        'payment.return',
        'payment.cancel',
        'public.file',
        'public.folder',
    ],

    'menu' => [
        'dashboard',
        'profile.password.edit',
        'profile.two-factor.edit',
        'profile.connections',
        'currencies.index',
        'notification-audiences.index',
        'notification-audiences.edit',
        'activity-logs.index',
        'activity-logs.show',
        'error-logs.index',
        'error-logs.show',
        'notifications.index',
        'settings.index',
        'settings.group',
        'settings.platform',
        'domain-access.index',
        'users.index',
        'users.create',
        'users.edit',
        'menus.index',
        'menus.create',
        'menus.edit',
        'menus.trashed',
        'inference.model-cards.index',
        'inference.model-cards.create',
        'inference.model-cards.edit',
        'inference.model-cards.show',
        'inference.providers.index',
        'inference.providers.show',
        'inference.providers.create',
        'inference.providers.edit',
        'inference.usage-events.index',
        'ai.connections.index',
        'ai.connections.create',
        'ai.connections.edit',
        'payment-gateways.index',
        'payment-gateways.create',
        'payment-gateways.edit',
        'subscriptions.index',
        'subscriptions.create',
        'subscriptions.edit',
        'subscriptions.show',
        'services.index',
        'services.create',
        'services.edit',
        'services.show',
        'invoices.index',
        'invoices.show',
        'product-activations.index',
        'product-activations.show',
        'payments.income.index',
        'payment-channels.index',
        'service-purchases.show',
    ],
];
