<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core hub
    |--------------------------------------------------------------------------
    |
    | The kernel ships the contract, never a client. A consumer that talks to a
    | core hub publishes the values below from its own provider and binds
    | `App\Support\CoreHub\Contracts\CoreHubClient`; a host with no hub leaves
    | everything empty and boots identically.
    |
    */

    'client' => env('CORE_HUB_CLIENT'),

    'hub' => [
        // Origin of the hub's internal API, e.g. https://hub.example.test.
        'base_url' => env('CORE_HUB_URL'),

        // This install's consumer key, as registered on the hub.
        'consumer' => env('CORE_HUB_CONSUMER'),

        // Product key whose entitlement this install consumes.
        'product' => env('CORE_HUB_PRODUCT'),

        'timeout' => (int) env('CORE_HUB_TIMEOUT', 5),
        'connect_timeout' => (int) env('CORE_HUB_CONNECT_TIMEOUT', 2),
        'retries' => (int) env('CORE_HUB_RETRIES', 1),
    ],

    'inbound' => [
        // Per-consumer secrets this install accepts pushes with. The kernel
        // verifies the HMAC; a hub rotates one entry at a time.
        'consumers' => [],

        // Tolerance window for a pushed `X-Core-Hub-Timestamp`, in seconds.
        'tolerance' => 300,
    ],

    'paths' => [
        // Mounted by the host under the `internal` middleware group, never under
        // the public API. A hub and a consumer both serve them; nothing a
        // browser reaches is ever here.
        'identity' => 'internal/v1/identity/sync',
        'entitlement' => 'internal/v1/entitlement/change',
    ],
];
