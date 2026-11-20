<?php

/**
 * Kernel defaults for the hCaptcha human-verification gate.
 *
 * Identity's auth requests read `hcaptcha.*` through `setting_config()`, so a
 * kernel-only install has to ship the block or every captcha lookup resolves
 * null and `HcaptchaVerifier` posts to an empty URI. The host keeps its own
 * copy with the same keys; `mergeConfigFrom` is shallow, so the host wins.
 */
return [

    'enabled' => false,

    'secret' => env('HCAPTCHA_SECRET'),

    'sitekey' => env('HCAPTCHA_SITEKEY'),

    'verify_url' => 'https://api.hcaptcha.com/siteverify',

];
