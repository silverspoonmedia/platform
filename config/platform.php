<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kernel package options
    |--------------------------------------------------------------------------
    |
    | Read by `silverspoonmedia/platform` when it is installed as a package. A
    | host may publish this file (`php artisan vendor:publish`) to override any
    | of them; the monolith does not, because its own `config/` wins.
    |
    */

    // Directory that receives the kernel's migrations when the host runs
    // `php artisan vendor:publish --tag=silverspoon-platform-migrations`.
    'migrations_path' => 'migrations',

];
