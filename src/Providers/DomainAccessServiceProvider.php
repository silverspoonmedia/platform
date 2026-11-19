<?php

namespace App\Providers;

use App\Support\DomainAccess\Http\Middleware\DomainAccessGate;
use Illuminate\Support\ServiceProvider;

class DomainAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('domain.access', DomainAccessGate::class);
    }
}
