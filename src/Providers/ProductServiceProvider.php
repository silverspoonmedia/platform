<?php

namespace App\Providers;

use App\Support\Product\Http\Middleware\ProductActive;
use App\Support\Product\Models\ProductActivation;
use App\Support\Product\Policies\ProductActivationPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['router']->aliasMiddleware('product.active', ProductActive::class);

        Gate::policy(ProductActivation::class, ProductActivationPolicy::class);
    }
}
