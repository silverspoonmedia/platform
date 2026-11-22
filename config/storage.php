<?php

/**
 * Kernel-owned storage mechanics.
 *
 * `silverspoonmedia/platform` ships the direct-upload engine, so the engine's own
 * knobs live here, not in a product's config file. Empty by default: a
 * kernel-only install presigns directly against S3 (see
 * {@see \App\Support\Storage\S3DirectUploadEngine::isUploadProxyEnabled()}) and
 * logs no upstream detail.
 *
 * A product that routes uploads through its own Worker publishes the values from
 * its provider — `config(['storage.upload_proxy' => config('archivd.upload_proxy')])`
 * is the whole seam, and removing the product leaves the defaults in place.
 */
return [
    /*
    |--------------------------------------------------------------------------
    | Upload proxy (optional)
    |--------------------------------------------------------------------------
    |
    | When `base_url` is set, the engine builds Worker PUT / UploadPart URLs
    | instead of browser S3 presigns. `disk_targets` maps a Laravel disk name to
    | the proxy's own target alias; a disk with no alias fails closed.
    |
    */
    'upload_proxy' => [
        'base_url' => '',
        'token' => '',
        'disk_targets' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug logging
    |--------------------------------------------------------------------------
    |
    | Log upstream S3 exception detail (headers, command params) when an upload
    | fails. Never enable in production: headers may contain sensitive values.
    |
    */
    'debug_log_requests' => false,
];
