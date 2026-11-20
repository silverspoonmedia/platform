<?php

namespace App\Providers;

use App\Support\Ai\AiUsageConsolidatorRegistry;
use App\Support\Billing\FulfillmentRegistry;
use App\Support\Broadcasting\ChannelRegistrarRegistry;
use App\Support\Encryption\LegacyColumnMigratorRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

/**
 * The kernel's runtime surface, when it is installed as a package.
 *
 * The host has `AppServiceProvider`, which ships the platform as an app; a
 * consumer installs `silverspoonmedia/platform` and gets this provider instead.
 * It carries only what a package needs to work outside the monolith: factory
 * discovery, the package's migrations (publishable, not auto-loaded — the host
 * owns its schema), and the two storage directories Laravel assumes exist.
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

        // Kernel-owned registries: a packaged domain publishes seams into these
        // from its own provider, so neither the kernel nor a package imports the
        // other's classes. Singleton is correct — they are pure registration
        // maps, and a second instance would silently drop every registration.
        // The host binds the same four in `AppServiceProvider`; a consumer
        // installs the platform and gets them here.
        $this->app->singleton(FulfillmentRegistry::class);
        $this->app->singleton(LegacyColumnMigratorRegistry::class);
        $this->app->singleton(ChannelRegistrarRegistry::class);
        $this->app->singleton(AiUsageConsolidatorRegistry::class);
    }

    public function boot(): void
    {
        $this->ensureStorageDirectories();

        if ($this->app->runningInConsole()) {
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
