<?php

return [
    // Application-owned mail copy. The shared password-reset / verify-email
    // lines come from the laravel-lang JSON packs; only project-specific lines
    // live here. Keep every key translated in all supported locales.
    'reset_password_api_hint' => 'APIを直接利用する場合は、トークンとメールアドレスを使って POST /api/v1/auth/reset-password を呼び出してください。',
];
