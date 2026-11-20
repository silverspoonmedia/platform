<?php

namespace App\Support\Testing;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\OptionalJwtAuthenticate;
use App\Http\Middleware\SetLocale;
use App\Providers\Domain\IdentityServiceProvider;
use App\Providers\DomainAccessServiceProvider;
use App\Providers\EncryptionServiceProvider;
use App\Providers\KernelServiceProvider;
use App\Providers\ProductServiceProvider;
use App\Providers\SettingsServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Orchestra\Testbench\TestCase as TestbenchTestCase;
use PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider;
use PragmaRX\Google2FALaravel\ServiceProvider;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Spatie\Permission\PermissionServiceProvider;

/**
 * The base a package test extends so the package runs without the monolith.
 *
 * A `Package:{slug}` suite executes against whatever checkout hosts it. When that
 * checkout is the monolith, `Tests\TestCase` boots the full application — fine,
 * but it also means the published package's tests reference `Tests\*`, a
 * namespace that never ships, so a consumer who clones a mirror tag cannot run
 * them. This class boots Testbench instead: the kernel's providers, no
 * `bootstrap/providers.php`, no product provider, no host factory resolver
 * pointing at `database/factories`, no host `database/migrations`.
 *
 * The kernel schema arrives through {@see KernelSchema} rather than the host's
 * migration tree, so a package test needs nothing from the monolith except the
 * package directories themselves.
 *
 * The monolith keeps `Tests\TestCase` for `tests/**`: it is faster (one
 * application per process) and it is entitled to the host schema — product
 * tables included.
 */
class PackageTestCase extends TestbenchTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Resolved from the directory that hosts the suite so the monolith and a
        // cloned package both work — `__DIR__`, not `lang_path()`, because the
        // filesystem realpath here is the real package tree even when the
        // autoloader reached it through a junction. A standalone install has no
        // host `lang/` beside the package, so leave the default path in place:
        // each package still loads its own `resources/lang` through
        // `loadTranslationsFrom()`.
        if (is_dir(dirname(__DIR__, 5).'/lang')) {
            $this->app->useLangPath(dirname(__DIR__, 5).'/lang');
        }

        config([
            'encryption.kms_enabled' => false,
            'encryption.blind_index_pepper' => 'testing-blind-index-pepper',
            'encryption.encrypt_sessions' => false,
        ]);

        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    /**
     * The kernel providers only. A package that boots extra wiring (routes,
     * commands, listeners) adds them by overriding `getPackageProviders()` and
     * calling the parent — the registry that assembles them in the monolith is
     * host-owned and absent here on purpose.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaravelServiceProvider::class,
            PermissionServiceProvider::class,
            ActivitylogServiceProvider::class,
            KernelServiceProvider::class,
            EncryptionServiceProvider::class,
            ProductServiceProvider::class,
            DomainAccessServiceProvider::class,
            SettingsServiceProvider::class,
            IdentityServiceProvider::class,
            ServiceProvider::class,
        ];
    }

    /**
     * Serve each booted package's own endpoints on the same `api/v1` prefix and
     * route-name prefix the monolith uses, so a package test can hit its own
     * controller through the real middleware instead of whatever a checkout
     * happens to load.
     *
     * Only a payload actually installed boots: the route file is picked by
     * matching the `provider` basename in `config/domains.php` against the
     * providers this test declared, so `audit` serves both of its files,
     * `billing` serves all three domains and `ai` serves both of its domains.
     */
    protected function defineRoutes($router): void
    {
        $router->aliasMiddleware('role', RoleMiddleware::class);
        $router->aliasMiddleware('permission', PermissionMiddleware::class);
        $router->aliasMiddleware('role_or_permission', RoleOrPermissionMiddleware::class);
        $router->aliasMiddleware('jwt.optional', OptionalJwtAuthenticate::class);

        // Testbench boots a skeleton app, not this checkout, so `config()`,
        // `base_path()` and the auto-discovered domain registry all point at
        // vendor/. Read the checkout's own registry off disk and resolve each
        // route file from the checkout root.
        $root = dirname(__DIR__, 5);
        $domains = is_file($root.'/config/domains.php') ? (array) require $root.'/config/domains.php' : [];

        $booted = array_map(
            static fn (string $provider): string => class_basename($provider),
            $this->getPackageProviders($this->app),
        );

        $paths = [];

        foreach ($domains as $meta) {
            $routes = $meta['routes'] ?? null;
            $provider = $meta['provider'] ?? null;

            if (! is_string($routes) || ! is_string($provider)) {
                continue;
            }

            if (! in_array(pathinfo($provider, PATHINFO_FILENAME), $booted, true)) {
                continue;
            }

            $path = $root.'/'.$routes;

            if (is_file($path)) {
                $paths[$path] = $path;
            }
        }

        // The registry only exists in the checkout. A standalone install has no
        // `config/domains.php` beside the package, so each booted provider's
        // own `routes/` directory is the fallback — the file list a consumer's
        // tag ships. `web-*.php` files are mounted by `defineWebRoutes()`.
        if ($paths === []) {
            $paths = $this->packageRouteFiles($booted);
        }

        if ($paths === []) {
            return;
        }

        $router->middleware(['api', SetLocale::class])
            ->prefix('api/v1')
            ->name('api.v1.')
            ->group(function () use ($paths): void {
                foreach ($paths as $path) {
                    require $path;
                }
            });
    }

    /**
     * Server-side (non-API) route files: a provider's `routes/web-*.php`.
     *
     * Testbench mounts them under the `web` group, so a package test covers a
     * provider callback (Discord OAuth) without `routes/web.php`.
     */
    protected function defineWebRoutes($router): void
    {
        $root = dirname(__DIR__, 5);
        $domains = is_file($root.'/config/domains.php') ? (array) require $root.'/config/domains.php' : [];

        $booted = array_map(
            static fn (string $provider): string => class_basename($provider),
            $this->getPackageProviders($this->app),
        );

        $files = [];

        foreach ($domains as $meta) {
            $web = $meta['web'] ?? null;
            $provider = $meta['provider'] ?? null;

            if (! is_string($web) || ! is_string($provider) || ! is_file($root.'/'.$web)) {
                continue;
            }

            if (! in_array(pathinfo($provider, PATHINFO_FILENAME), $booted, true)) {
                continue;
            }

            $files[$web] = $root.'/'.$web;
        }

        if ($files === []) {
            foreach ($this->bootedProviderFiles($booted) as $providerFile) {
                $package = $this->packageRoot($providerFile);

                if ($package === null) {
                    continue;
                }

                foreach (glob($package.'/routes/web-*.php') ?: [] as $web) {
                    $files[$web] = $web;
                }
            }
        }

        foreach ($files as $file) {
            require $file;
        }
    }

    /**
     * Route files shipped beside each booted provider's package.
     *
     * Resolved by walking up from the provider file to the package root (the
     * nearest ancestor with a `composer.json`), because a provider is not always
     * at `{package}/src` — the kernel's Identity provider sits at
     * `src/Providers/Domain/`.
     *
     * @param  list<string>  $booted
     * @return array<string, string>
     */
    private function packageRouteFiles(array $booted): array
    {
        $paths = [];

        foreach ($this->bootedProviderFiles($booted) as $providerFile) {
            $package = $this->packageRoot($providerFile);

            if ($package === null) {
                continue;
            }

            foreach (glob($package.'/routes/*.php') ?: [] as $route) {
                if (str_starts_with(basename($route), 'web-')) {
                    continue;
                }

                $paths[$route] = $route;
            }
        }

        ksort($paths);

        return $paths;
    }

    /**
     * Absolute file of every booted provider, keyed by class name.
     *
     * @param  list<string>  $booted
     * @return array<string, string>
     */
    private function bootedProviderFiles(array $booted): array
    {
        $files = [];

        foreach ($this->getPackageProviders($this->app) as $provider) {
            if (! is_string($provider) || ! in_array(class_basename($provider), $booted, true)) {
                continue;
            }

            $file = (new \ReflectionClass($provider))->getFileName();

            if ($file !== false) {
                $files[class_basename($provider)] = $file;
            }
        }

        return $files;
    }

    /**
     * The nearest ancestor of a source file that carries a `composer.json`.
     */
    private function packageRoot(string $file): ?string
    {
        $directory = dirname($file);

        for ($depth = 0; $depth < 8; $depth++) {
            if (is_file($directory.'/composer.json')) {
                return $directory;
            }

            $parent = dirname($directory);

            if ($parent === $directory) {
                return null;
            }

            $directory = $parent;
        }

        return null;
    }

    /**
     * Environment the kernel assumes before any provider reads it.
     *
     * `JWT_SECRET` is load-bearing: without it the JWT guard extension is absent
     * and `JWTAuth` resolves as an undefined guard.
     */
    protected function defineEnvironment($app): void
    {
        // Pin the translation root before any provider boots: the host's
        // `TranslationServiceProvider` registers `lang_path()`, and Testbench's
        // default is a vendor directory, so every `__()` lookup a package makes
        // would render its key raw. Resolved from the directory that hosts the
        // suite so the monolith and a cloned package both work. A standalone
        // install has no host `lang/` beside the package, so the default path
        // stays and each package provider loads its own `resources/lang`
        // through `loadTranslationsFrom()`.
        if (is_dir(dirname(__DIR__, 5).'/lang')) {
            $app->useLangPath(dirname(__DIR__, 5).'/lang');
        }

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('app.key', 'base64:2fl+KtvkdphvQyEfmNqD2aE7nKj8vR3xW5yZ1aB4cD8=');
        $app['config']->set('jwt.secret', 'testing-jwt-secret-key-with-enough-length');
        $app['config']->set('auth.defaults.guard', 'api');
        $app['config']->set('auth.default_role', 'User');
        $app['config']->set('auth.guards', [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'jwt', 'provider' => 'users'],
        ]);
        $app['config']->set('auth.providers', [
            'users' => ['driver' => 'eloquent', 'model' => User::class],
        ]);
        $app['config']->set('auth.passwords', [
            'users' => ['provider' => 'users', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        ]);
        $app['config']->set('permission.guard_name', 'api');
        $app['config']->set('permission.models', [
            'permission' => Permission::class,
            'role' => Role::class,
        ]);
        $app['config']->set('permission.table_names', [
            'roles' => 'roles',
            'permissions' => 'permissions',
            'model_has_permissions' => 'model_has_permissions',
            'model_has_roles' => 'model_has_roles',
            'role_has_permissions' => 'role_has_permissions',
        ]);
        $app['config']->set('activitylog.enabled', false);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('mail.default', 'array');
        $app['config']->set('broadcasting.default', 'null');

        // Testbench ships its own skeleton `config/`, so a package rule that
        // reads a host config tree (`spa-routes.menu`, `localization.supported_locales`)
        // would see an empty array and fail a payload the monolith accepts.
        // Merge the checkout's own copies when the checkout has them; a cloned
        // package mirror falls back to the test-local defaults below.
        $root = dirname(__DIR__, 5);

        // `config/services.php` derives the Discord redirect from `app.url` at
        // require time, so the URL must already be the one a request sees.
        $app['config']->set('app.url', 'http://localhost');

        foreach (['spa-routes', 'localization', 'services'] as $name) {
            $file = $root.'/config/'.$name.'.php';

            // A standalone install has no host `config/`: the kernel's own
            // copy is the only one present, and it is the same tree.
            if (! is_file($file)) {
                $file = dirname(__DIR__, 3).'/config/'.$name.'.php';
            }

            if (is_file($file)) {
                $app['config']->set($name, (array) require $file);
            }
        }

        if ($app['config']->get('localization.supported_locales') === null) {
            $app['config']->set('localization.supported_locales', ['en', 'id', 'ja', 'ko', 'zh_CN', 'ar', 'ru']);
            $app['config']->set('localization.fallback_locale', 'en');
        }

        if ($app['config']->get('spa-routes.menu') === null) {
            $app['config']->set('spa-routes.menu', []);
            $app['config']->set('spa-routes.deny', []);
        }
    }

    /**
     * Create the kernel's slice of the schema, then the package's own.
     *
     * One entry point on purpose: a subclass overrides
     * `runExtraDatabaseMigrations()`, never this method, so the kernel tables
     * always exist before a package migration adds a foreign key to them, and its
     * `down()` always has a schema to inspect.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->runKernelMigrations();
        $this->runExtraDatabaseMigrations();
    }

    /**
     * Hook for a package's own migrations. Default: nothing, because the kernel
     * alone has none of its own to run here.
     */
    protected function runExtraDatabaseMigrations(): void
    {
        //
    }

    /**
     * Create the kernel's slice of the schema once per process.
     *
     * `KernelSchema` is keyed on `users`, so a suite that already has it (the
     * whole run shares one in-memory connection) does no work.
     */
    protected function runKernelMigrations(): void
    {
        KernelSchema::ensure();
    }

    /**
     * Load the migrations in a package's own `database/migrations` directory.
     *
     * Done by hand rather than `loadMigrationsFrom()` + `artisan migrate`, and
     * guarded by the migrator's own `migrations` table, so a package that boots
     * twice in one process does not create a table twice. `loadMigrationsFrom`
     * alone only registers a path; nothing runs it under Testbench unless the
     * base requests it.
     */
    protected function runPackageMigrations(string $directory): void
    {
        /** @var array<int, string> $files */
        $files = glob($directory.'/*.php') ?: [];

        $migrator = $this->app->make(Migrator::class);

        if (! $migrator->repositoryExists()) {
            $migrator->getRepository()->createRepository();
        }

        // `migrations` is a process-wide table on the shared in-memory
        // connection while each test class boots its own application, so the
        // repository is the only durable record that a file already ran.
        $ran = $migrator->getRepository()->getRan();
        $pending = array_values(array_filter(
            $files,
            static fn (string $file): bool => ! in_array($migrator->getMigrationName($file), $ran, true),
        ));

        if ($pending !== []) {
            $migrator->run($pending);
        }
    }

    /**
     * Factory discovery lives in the host's `AppServiceProvider`, which this base
     * never boots. A package ships its factories beside its models, so the
     * resolver points at the domain namespace first and falls back to the host's
     * `Database\Factories`.
     */
    public function afterApplicationCreated(callable $callback): void
    {
        // A package ships its factories beside the model it builds; the host
        // resolves them through its own `AppServiceProvider`, which never boots
        // here. Pointing the resolver at the domain namespace keeps
        // `User::factory()` (and every other kernel model) working.
        Factory::guessFactoryNamesUsing(static function (string $modelName): string {
            $parts = explode('\\', $modelName);

            if (($parts[0] ?? '') === 'App' && ($parts[1] ?? '') === 'Domain') {
                return 'App\\Domain\\'.$parts[2].'\\Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Database\\Factories\\'.class_basename($modelName).'Factory';
        });

        parent::afterApplicationCreated($callback);
    }
}
