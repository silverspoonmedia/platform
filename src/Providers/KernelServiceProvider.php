<?php

namespace App\Providers;

use App\Console\Commands\Domain\ListDomainsCommand;
use App\Console\Commands\EncryptionFlushDecryptedCacheCommand;
use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use App\Repositories\Eloquent\EloquentCrudRepository;
use App\Support\Ai\AiUsageConsolidatorRegistry;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use App\Support\Encryption\LegacyColumnMigratorRegistry;
use App\Support\Http\AllowlistCorsService;
use App\Support\Navigation\MenuContributorRegistry;
use App\Support\Settings\SettingCatalog;
use Fruitcake\Cors\CorsService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * The kernel's runtime surface, when it is installed as a package.
 *
 * The host has `AppServiceProvider`, which ships the platform as an app; a
 * consumer installs `silverspoonmedia/platform` and gets this provider instead.
 * It carries only what a package needs to work outside the monolith: the shared
 * repository, the CORS service `HandleCors` injects, the kernel-owned seam
 * registries, the package's migrations (publishable, not auto-loaded — the host
 * owns its schema), and the storage directories Laravel assumes exist.
 *
 * **A consumer must list this class in `bootstrap/providers.php`.** It is
 * deliberately absent from `extra.laravel.providers`: auto-discovery would boot
 * it in the monolith too, where `AppServiceProvider` already owns these
 * bindings, and a second pass after a seam registry resolved would replace the
 * singleton and silently drop every registration.
 */
class KernelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/platform.php', 'platform');

        // The SPA route registry and the locale policy are kernel policy, not
        // host policy: Navigation validates a menu `route` against this list and
        // Identity stores `users.locale` against it. A consumer installs the
        // kernel alone, so both have to ship here; `mergeConfigFrom` is shallow
        // and the host's own copy still wins.
        $this->mergeConfigFrom(__DIR__.'/../../config/spa-routes.php', 'spa-routes');
        $this->mergeConfigFrom(__DIR__.'/../../config/localization.php', 'localization');

        // Discord + SPA origin: Identity's OAuth callback and every signed mail
        // link read these, and a kernel-only install has no host `services.php`.
        $this->mergeConfigFrom(__DIR__.'/../../config/services.php', 'services');

        // Storage mechanics the kernel ships (the direct-upload engine). Empty by
        // default; a product publishes its upload proxy into `storage.*` instead
        // of the kernel reading the product's own config group.
        $this->mergeConfigFrom(__DIR__.'/../../config/storage.php', 'storage');

        // Kernel-owned registries: a packaged domain publishes seams into these
        // from its own provider, so neither the kernel nor a package imports the
        // other's classes. Singleton is correct — they are pure registration
        // maps, and a second instance would silently drop every registration.
        // The host binds the same four in `AppServiceProvider`; a consumer
        // installs the platform and gets them here.
        //
        // `bound()` guards, not bare `singleton()` calls: `singleton()` forgets
        // any resolved instance, so registering twice would discard the seam
        // registrations a package already published. In the monolith the host
        // provider runs first and these become no-ops; in a consumer install the
        // host has no such provider and this file is the only source.
        $this->singletonOnce(FulfillmentRegistry::class);
        $this->singletonOnce(LegacyColumnMigratorRegistry::class);
        $this->singletonOnce(ChannelRegistrarRegistry::class);
        $this->singletonOnce(AiUsageConsolidatorRegistry::class);
        $this->singletonOnce(SettingCatalog::class);
        $this->singletonOnce(MenuContributorRegistry::class);

        if (! $this->app->bound(EloquentCrudRepositoryInterface::class)) {
            $this->app->bind(EloquentCrudRepositoryInterface::class, EloquentCrudRepository::class);
        }

        // `HandleCors` injects this service. The stock implementation answers a
        // single-origin allowlist without checking the request origin; see the
        // class docblock for why that must not reach the browser.
        if (! $this->app->bound(CorsService::class)) {
            $this->app->bind(CorsService::class, AllowlistCorsService::class);
        }
    }

    /**
     * Bind a seam registry only when nothing already answered for it.
     *
     * `singleton()` preserves no instance, so a duplicate call after a package
     * registered a fulfillment handler would empty the map.
     *
     * @param  class-string  $abstract
     */
    private function singletonOnce(string $abstract): void
    {
        if ($this->app->bound($abstract)) {
            return;
        }

        $this->app->singleton($abstract);
    }

    public function boot(): void
    {
        $this->ensureStorageDirectories();

        if ($this->app->runningInConsole()) {
            // Both commands edit nothing durable: `domain:list` reads the
            // registry and the install paths, and the cache flush forgets one
            // key. `domain:make` / `domain:remove` rewrite host trees and stay
            // host-owned.
            $this->commands([
                ListDomainsCommand::class,
                EncryptionFlushDecryptedCacheCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../database/migrations' => database_path((string) config('platform.migrations_path', 'migrations')),
            ], 'silverspoon-platform-migrations');
        }
    }

    private function ensureStorageDirectories(): void
    {
        foreach ([
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ] as $directory) {
            if (! is_dir($directory)) {
                File::makeDirectory($directory, 0777, true, true);
            }
        }
    }
}
