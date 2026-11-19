<?php

namespace App\Support\Domain;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Path helpers and source-file edits for domain:list / domain:make / domain:remove.
 *
 * Keeps config/domains.php, bootstrap/providers.php, and route loaders in sync.
 * Does not touch migrations (DB history stays intact on remove).
 *
 * Registry axes (see config/domains.php):
 * - kind: core | product (taxonomy)
 * - locked: product-only removal guard (lifecycle)
 * - parent: product-only composition axis — a subproduct points at its product parent
 *
 * Platform core is hardcoded — flipping kind in config cannot unlock remove.
 * Product domains unlock only by editing locked => false by hand.
 */
class DomainManager
{
    /**
     * Boilerplate domains that domain:remove always refuses.
     * Config cannot override this list.
     *
     * @var list<string>
     */
    public const PLATFORM_CORE = [
        'Identity',
        'Navigation',
        'Notification',
        'ActivityLog',
        'ErrorLog',
        'PaymentGateway',
        'Currency',
        'Service',
        'Inference',
        'Ai',
    ];

    /** @var list<string> */
    private const RESERVED = [
        'Kernel',
        'Support',
        'Http',
        'Providers',
        'Console',
        'Exceptions',
        'Repositories',
        'Rules',
        'View',
        'Domain',
    ];

    public function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Domain name cannot be empty.');
        }

        if (str_contains($name, '-') || str_contains($name, '_')) {
            return Str::studly($name);
        }

        if (preg_match('/^[A-Z][A-Za-z0-9]*$/', $name) === 1) {
            return $name;
        }

        if (preg_match('/^[a-z][a-z0-9]*$/', $name) === 1) {
            return Str::studly($name);
        }

        throw new InvalidArgumentException(
            "Invalid domain name [{$name}]. Use StudlyCase (e.g. Billing) or kebab-case (e.g. activity-log)."
        );
    }

    public function slug(string $name): string
    {
        return Str::kebab($this->normalizeName($name));
    }

    public function isReserved(string $name): bool
    {
        return in_array($this->normalizeName($name), self::RESERVED, true);
    }

    public function isRegistered(string $name): bool
    {
        $name = $this->normalizeName($name);

        return array_key_exists($name, $this->all());
    }

    /**
     * Hardcoded platform core — config cannot override.
     */
    public function isPlatformCore(string $name): bool
    {
        return in_array($this->normalizeName($name), self::PLATFORM_CORE, true);
    }

    /**
     * Taxonomy: core foundation domain (PLATFORM_CORE or kind => core in config).
     * Prefer isPlatformCore() for remove guards.
     */
    public function isCore(string $name): bool
    {
        return $this->kind($name) === 'core';
    }

    /**
     * Taxonomy: product / SaaS surface domain.
     */
    public function isProduct(string $name): bool
    {
        return $this->kind($name) === 'product';
    }

    /**
     * Product removal lock from config. Core domains are never "locked" (use isPlatformCore).
     */
    public function isLocked(string $name): bool
    {
        if ($this->isPlatformCore($name)) {
            return false;
        }

        $name = $this->normalizeName($name);
        $all = $this->all();

        if (! array_key_exists($name, $all)) {
            return false;
        }

        return (bool) ($all[$name]['locked'] ?? false);
    }

    /**
     * Taxonomy label for domain:list — core | product only.
     */
    public function kind(string $name): string
    {
        if ($this->isPlatformCore($name)) {
            return 'core';
        }

        $name = $this->normalizeName($name);
        $all = $this->all();

        if (! array_key_exists($name, $all)) {
            return 'product';
        }

        $kind = $all[$name]['kind'] ?? 'product';

        return $kind === 'core' ? 'core' : 'product';
    }

    /**
     * Composition axis: the parent product domain this domain extends, if any.
     *
     * A subproduct is still a full product — this only records the hierarchy.
     */
    public function parentOf(string $name): ?string
    {
        $name = $this->normalizeName($name);
        $all = $this->all();

        return $all[$name]['parent'] ?? null;
    }

    /**
     * Registered subproducts pointing at this domain, in registry order.
     *
     * @return list<string>
     */
    public function childrenOf(string $parent): array
    {
        $parent = $this->normalizeName($parent);

        return array_values(array_keys(array_filter(
            $this->all(),
            static fn (array $meta): bool => $meta['parent'] === $parent
        )));
    }

    /**
     * Lifecycle label for domain:list — never | locked | removable.
     */
    public function removalStatus(string $name): string
    {
        if ($this->isPlatformCore($name) || $this->kind($name) === 'core') {
            return 'never';
        }

        if ($this->isLocked($name)) {
            return 'locked';
        }

        return 'removable';
    }

    public function directoryExists(string $name): bool
    {
        return File::isDirectory($this->domainPath($name));
    }

    /**
     * @return array<string, array{kind: string, locked: bool, parent: string|null}>
     */
    public function all(): array
    {
        /** @var array<string, array{kind?: string, core?: bool, locked?: bool, parent?: string|null}> $raw */
        $raw = (array) config('domains', []);
        $out = [];

        foreach ($raw as $name => $meta) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            if (! is_array($meta)) {
                $meta = [];
            }

            $platform = in_array($name, self::PLATFORM_CORE, true);
            $kind = $this->resolveKindFromMeta($meta, $platform);

            $out[$name] = [
                'kind' => $kind,
                'locked' => $kind === 'product' && (bool) ($meta['locked'] ?? false),
                'parent' => $this->resolveParentFromMeta($meta, $kind),
            ];
        }

        return $out;
    }

    public function domainPath(string $name): string
    {
        $name = $this->normalizeName($name);
        $configured = $this->configuredPath($name);

        return $configured !== null ? base_path($configured) : app_path('Domain/'.$name);
    }

    public function providerPath(string $name): string
    {
        $name = $this->normalizeName($name);
        $configured = $this->configuredProviderPath($name);

        if ($configured !== null) {
            return base_path($configured);
        }

        return app_path('Providers/Domain/'.$name.'ServiceProvider.php');
    }

    /**
     * Package manifest of a core domain shipped as a Composer path package.
     *
     * A packaged core domain keeps its `App\Domain\{Name}\` namespace, so the code
     * that consumes it does not change; only where the source lives moves. That
     * path is declared in config/domains.php — one source of truth for
     * domain:list, domain:make, domain:remove and the route loader.
     *
     * @return array{package: string, path: string, routes: string, lang: string|null}|null
     */
    public function packageOf(string $name): ?array
    {
        $meta = (array) config('domains.'.$this->normalizeName($name), []);

        if (! isset($meta['package']) || ! is_string($meta['package']) || $meta['package'] === '') {
            return null;
        }

        return [
            'package' => $meta['package'],
            'path' => (string) ($meta['path'] ?? ''),
            'routes' => (string) ($meta['routes'] ?? ''),
            'lang' => isset($meta['lang']) && is_string($meta['lang']) ? $meta['lang'] : null,
        ];
    }

    public function isPackaged(string $name): bool
    {
        return $this->packageOf($name) !== null;
    }

    /**
     * Route files owned by packaged core domains, in core-first registry order.
     *
     * The loader in routes/api/v1.php requires these instead of hardcoding a path
     * that only exists in this checkout.
     *
     * @return list<string>
     */
    public function packageApiRoutePaths(): array
    {
        $paths = [];

        foreach ($this->all() as $name => $meta) {
            if ($meta['kind'] !== 'core') {
                continue;
            }

            $package = $this->packageOf($name);

            if ($package === null || $package['routes'] === '') {
                continue;
            }

            $paths[] = base_path($package['routes']);
        }

        return $paths;
    }

    /**
     * Web (server-side, non-SPA) route files owned by packaged core domains.
     *
     * `routes/web.php` requires these instead of hardcoding a provider-return
     * path that only exists in this checkout. A package entry declares it with
     * the `web` key; entries without one contribute nothing.
     *
     * @return list<string>
     */
    public function packageWebRoutePaths(): array
    {
        $paths = [];

        foreach ($this->all() as $name => $meta) {
            if ($meta['kind'] !== 'core') {
                continue;
            }

            $web = config('domains.'.$name.'.web');

            if (! is_string($web) || $web === '') {
                continue;
            }

            $paths[] = base_path($web);
        }

        return $paths;
    }

    private function configuredPath(string $name): ?string
    {
        $package = $this->packageOf($name);

        if ($package === null || $package['path'] === '') {
            return null;
        }

        return $package['path'];
    }

    private function configuredProviderPath(string $name): ?string
    {
        $meta = (array) config('domains.'.$name, []);
        $provider = $meta['provider'] ?? null;

        return is_string($provider) && $provider !== '' ? $provider : null;
    }

    public function providerShortClass(string $name): string
    {
        return $this->normalizeName($name).'ServiceProvider';
    }

    public function apiRoutePath(string $name): string
    {
        $package = $this->packageOf($name);

        if ($package !== null && $package['routes'] !== '') {
            return base_path($package['routes']);
        }

        return base_path('routes/api/v1/'.$this->slug($name).'.php');
    }

    public function webRoutePath(string $name): string
    {
        return base_path('routes/web/'.$this->slug($name).'.php');
    }

    public function testsPath(string $name): string
    {
        $package = $this->packageOf($name);

        if ($package !== null && $package['path'] !== '') {
            // packages/{slug}/src -> packages/{slug}/tests
            return dirname(base_path($package['path'])).'/tests';
        }

        return base_path('tests/Domain/'.$this->normalizeName($name));
    }

    public function roleSeederShortClass(string $name): string
    {
        return $this->normalizeName($name).'RoleSeeder';
    }

    public function roleSeederPath(string $name): string
    {
        return database_path('seeders/'.$this->roleSeederShortClass($name).'.php');
    }

    public function scaffold(string $name, bool $locked = true, ?string $parent = null): void
    {
        $name = $this->normalizeName($name);

        if ($this->isReserved($name)) {
            throw new InvalidArgumentException("[{$name}] is reserved for the application kernel.");
        }

        if ($this->isPlatformCore($name)) {
            throw new InvalidArgumentException("[{$name}] is platform core and cannot be scaffolded again.");
        }

        if ($this->isRegistered($name) || $this->directoryExists($name)) {
            throw new InvalidArgumentException("Domain [{$name}] already exists.");
        }

        $domainPath = $this->domainPath($name);
        File::ensureDirectoryExists($domainPath.'/Http/Controllers/API/V1');
        File::ensureDirectoryExists($domainPath.'/Http/Controllers/Web');
        File::ensureDirectoryExists($domainPath.'/Http/Requests');
        File::ensureDirectoryExists($domainPath.'/Http/Resources');
        File::ensureDirectoryExists($domainPath.'/Services');
        File::ensureDirectoryExists($domainPath.'/Repositories/Contracts');
        File::ensureDirectoryExists($domainPath.'/Repositories/Eloquent');
        File::ensureDirectoryExists($domainPath.'/Models');

        $this->writeSourceFile($this->providerPath($name), $this->providerStub($name));
        $this->writeSourceFile($this->apiRoutePath($name), $this->routeStub($name, 'API'));
        $this->writeSourceFile($this->webRoutePath($name), $this->routeStub($name, 'Web'));
        $this->writeSourceFile($this->roleSeederPath($name), $this->roleSeederStub($name));

        $testsFeature = $this->testsPath($name).'/Feature';
        File::ensureDirectoryExists($testsFeature);
        // A placeholder, not a source file — it has to stay empty.
        File::put($testsFeature.'/.gitkeep', '');

        $this->registerInConfig($name, kind: 'product', locked: $locked, parent: $parent);
        $this->registerProvider($name);
        $this->registerApiV1Require($name);
        $this->registerWebRequire($name);
        $this->registerDomainSeeder($name);
        $this->writeUninstallDraft($name);
    }

    /**
     * Seed app/Domain/{Name}/uninstall.json from the live scan, so the new domain
     * starts with an accurate removal mapping instead of one written from memory
     * at removal time. See UninstallManifest.
     */
    private function writeUninstallDraft(string $name): void
    {
        $manifest = app(UninstallManifest::class);

        if (! $manifest->exists($name)) {
            $manifest->writeDraft($name);
        }
    }

    /**
     * Structural removal of an unlocked product domain.
     *
     * The composition guard (refuse while a registered subproduct points here) is
     * enforced by RemoveDomainCommand — it needs dry-run/--force semantics that do
     * not belong here. Call childrenOf() first when you need the same check.
     *
     * @return list<string> Human-readable leftover warnings
     */
    public function remove(string $name): array
    {
        $name = $this->normalizeName($name);

        if (! $this->isRegistered($name) && ! $this->directoryExists($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is not registered and has no app/Domain/{$name} directory."
            );
        }

        if ($this->isPlatformCore($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is platform core and cannot be removed. Config cannot override this."
            );
        }

        if ($this->isLocked($name)) {
            throw new InvalidArgumentException(
                "Domain [{$name}] is locked. Set 'locked' => false in config/domains.php manually, then re-run domain:remove."
            );
        }

        $warnings = $this->leftoverWarnings($name);

        $this->unregisterFromConfig($name);
        $this->unregisterProvider($name);
        $this->unregisterApiRequires($name);
        $this->unregisterWebRequire($name);
        $this->unregisterDomainSeeder($name);
        $this->stripBootstrapDiscoveries($name);

        if (File::isDirectory($this->domainPath($name))) {
            File::deleteDirectory($this->domainPath($name));
        }

        if (File::isDirectory($this->testsPath($name))) {
            File::deleteDirectory($this->testsPath($name));
        }

        if (File::isFile($this->providerPath($name))) {
            File::delete($this->providerPath($name));
        }

        foreach (array_unique([
            $this->apiRoutePath($name),
            base_path('routes/api/v1/'.$this->slug($name).'.php'),
            base_path('routes/api/'.$this->slug($name).'.php'),
        ]) as $routeFile) {
            if (File::isFile($routeFile)) {
                File::delete($routeFile);
            }
        }

        if (File::isFile($this->webRoutePath($name))) {
            File::delete($this->webRoutePath($name));
        }

        if (File::isFile($this->roleSeederPath($name))) {
            File::delete($this->roleSeederPath($name));
        }

        return $warnings;
    }

    /**
     * @param  'core'|'product'  $kind
     * @param  string|null  $parent  Composition axis — product only (see config/domains.php)
     */
    public function registerInConfig(string $name, string $kind = 'product', bool $locked = true, ?string $parent = null): void
    {
        $name = $this->normalizeName($name);
        $path = config_path('domains.php');
        $contents = File::get($path);

        if (preg_match("/['\"]".preg_quote($name, '/')."['\"]\\s*=>/", $contents) === 1) {
            return;
        }

        if ($kind === 'core') {
            $entry = "    '{$name}' => ['kind' => 'core'],\n];";
            $runtime = ['kind' => 'core'];
        } else {
            $lockedLiteral = $locked ? 'true' : 'false';
            $parentLiteral = '';

            if ($parent !== null && trim($parent) !== '') {
                $parentLiteral = ", 'parent' => '".$this->normalizeName($parent)."'";
            }

            $entry = "    '{$name}' => ['kind' => 'product', 'locked' => {$lockedLiteral}{$parentLiteral}],\n];";
            $runtime = ['kind' => 'product', 'locked' => $locked];

            if ($parentLiteral !== '') {
                $runtime['parent'] = $this->normalizeName($parent);
            }
        }

        $updated = preg_replace('/\];\s*$/', $entry, $contents, 1);
        if ($updated === null || $updated === $contents) {
            throw new RuntimeException('Could not update config/domains.php — closing ]; not found.');
        }

        $this->writeSourceFile($path, $updated);
        config(["domains.{$name}" => $runtime]);
    }

    public function unregisterFromConfig(string $name): void
    {
        $name = $this->normalizeName($name);
        $path = config_path('domains.php');
        $contents = File::get($path);

        $updated = preg_replace(
            "/\n\s*['\"]".preg_quote($name, '/')."['\"]\\s*=>\\s*\\[[^\\]]*\\],?/",
            '',
            $contents,
            1
        );

        if ($updated === null) {
            throw new RuntimeException("Could not remove [{$name}] from config/domains.php.");
        }

        $this->writeSourceFile($path, $updated);

        $all = config('domains', []);
        if (is_array($all)) {
            unset($all[$name]);
            config(['domains' => $all]);
        }
    }

    public function registerProvider(string $name): void
    {
        $name = $this->normalizeName($name);
        $short = $this->providerShortClass($name);
        $path = base_path('bootstrap/providers.php');
        $contents = File::get($path);

        if (str_contains($contents, $short.'::class')) {
            return;
        }

        if (! str_contains($contents, "use App\\Providers\\Domain\\{$short};")) {
            if (preg_match('/use App\\\\Providers\\\\Domain\\\\[A-Za-z0-9_]+;/m', $contents) === 1) {
                $contents = preg_replace(
                    '/(use App\\\\Providers\\\\Domain\\\\[A-Za-z0-9_]+;\n)(?!use App\\\\Providers\\\\Domain\\\\)/',
                    "$1use App\\Providers\\Domain\\{$short};\n",
                    $contents,
                    1
                ) ?? $contents;
            } else {
                $contents = preg_replace(
                    '/(use App\\\\Providers\\\\AppServiceProvider;\n)/',
                    "$1use App\\Providers\\Domain\\{$short};\n",
                    $contents,
                    1
                ) ?? $contents;
            }
        }

        $classLine = "    {$short}::class,\n];";
        $updated = preg_replace('/\];\s*$/', $classLine, $contents, 1);
        if ($updated === null || $updated === $contents) {
            throw new RuntimeException('Could not register provider in bootstrap/providers.php.');
        }

        $this->writeSourceFile($path, $updated);
    }

    public function unregisterProvider(string $name): void
    {
        $name = $this->normalizeName($name);
        $short = $this->providerShortClass($name);
        $path = base_path('bootstrap/providers.php');
        $contents = File::get($path);

        $contents = str_replace("\nuse App\\Providers\\Domain\\{$short};", '', $contents);
        $contents = str_replace("\n    {$short}::class,", '', $contents);

        $this->writeSourceFile($path, $contents);
    }

    public function registerApiV1Require(string $name): void
    {
        $slug = $this->slug($name);
        $path = base_path('routes/api/v1.php');
        $line = "require __DIR__.'/v1/{$slug}.php';";
        $contents = File::get($path);

        if (str_contains($contents, $line)) {
            return;
        }

        $this->writeSourceFile($path, $this->appendLine($contents, $line));
    }

    public function registerWebRequire(string $name): void
    {
        $slug = $this->slug($name);
        $path = base_path('routes/web.php');
        $line = "require __DIR__.'/web/{$slug}.php';";
        $contents = File::get($path);

        if (str_contains($contents, $line)) {
            return;
        }

        $anchor = '// Product domain web routes (optional server endpoints; appended by domain:make).';
        if (str_contains($contents, $anchor)) {
            $updated = str_replace($anchor, $anchor."\n{$line}", $contents);
        } else {
            $updated = $this->appendLine($contents, $line);
        }

        if ($updated === $contents) {
            throw new RuntimeException('Could not register web route require in routes/web.php.');
        }

        $this->writeSourceFile($path, $updated);
    }

    /**
     * Inject a `$this->call({Name}RoleSeeder::class);` line into
     * DatabaseSeeder::run so the generated product RBAC seeder runs. Anchored
     * before DomainAccessSeeder (which only handles the built-in products).
     */
    public function registerDomainSeeder(string $name): void
    {
        $short = $this->roleSeederShortClass($name);
        $path = database_path('seeders/DatabaseSeeder.php');

        if (! File::isFile($path)) {
            return;
        }

        $contents = File::get($path);
        $call = "        \$this->call({$short}::class);";

        if (str_contains($contents, "{$short}::class")) {
            return;
        }

        $anchor = '        $this->call(DomainAccessSeeder::class);';

        if (str_contains($contents, $anchor)) {
            $updated = str_replace($anchor, $call."\n".$anchor, $contents);
        } else {
            // Fallback: append after RoleSeeder call.
            $updated = preg_replace(
                '/(\$this->call\(RoleSeeder::class\);\n)/',
                "$1{$call}\n",
                $contents,
                1
            ) ?? $contents;
        }

        if ($updated !== $contents) {
            $this->writeSourceFile($path, $updated);
        }
    }

    public function unregisterDomainSeeder(string $name): void
    {
        $short = $this->roleSeederShortClass($name);
        $path = database_path('seeders/DatabaseSeeder.php');

        if (! File::isFile($path)) {
            return;
        }

        $this->removeLinesContaining($path, "\$this->call({$short}::class);");
    }

    public function unregisterApiRequires(string $name): void
    {
        $name = $this->normalizeName($name);
        $slug = $this->slug($name);

        $v1 = base_path('routes/api/v1.php');
        if (File::isFile($v1)) {
            $this->removeLinesContaining($v1, "require __DIR__.'/v1/{$slug}.php';");
        }
    }

    public function unregisterWebRequire(string $name): void
    {
        $slug = $this->slug($name);
        $this->removeLinesContaining(
            base_path('routes/web.php'),
            "require __DIR__.'/web/{$slug}.php';"
        );
    }

    public function stripBootstrapDiscoveries(string $name): void
    {
        $name = $this->normalizeName($name);
        $path = base_path('bootstrap/app.php');
        $contents = File::get($path);

        $needles = [
            "__DIR__.'/../app/Domain/{$name}/Listeners'",
            "__DIR__.'/../app/Domain/{$name}/Console/Commands'",
        ];

        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $filtered = [];

        foreach ($lines as $line) {
            $trim = trim($line);
            $drop = false;
            foreach ($needles as $needle) {
                if (str_contains($trim, $needle)) {
                    $drop = true;
                    break;
                }
            }
            if (! $drop) {
                $filtered[] = $line;
            }
        }

        $this->writeSourceFile($path, implode("\n", $filtered));
    }

    /**
     * @return list<string>
     */
    public function leftoverWarnings(string $name): array
    {
        $name = $this->normalizeName($name);
        $warnings = [];

        foreach ([database_path('factories'), database_path('seeders')] as $dir) {
            if (! File::isDirectory($dir)) {
                continue;
            }
            foreach (File::allFiles($dir) as $file) {
                $text = File::get($file->getPathname());
                if (str_contains($text, "App\\Domain\\{$name}\\") || str_contains($text, "Domain\\{$name}\\")) {
                    $warnings[] = 'Possible leftover reference in '.$file->getRelativePathname();
                }
            }
        }

        $warnings[] = 'Migrations were not deleted — review database/migrations for '.$name.' tables if you no longer need them.';
        $warnings[] = 'If menus or permissions referenced this domain, clean them up manually.';

        return $warnings;
    }

    /**
     * @param  array{kind?: string, core?: bool, locked?: bool}  $meta
     */
    private function resolveKindFromMeta(array $meta, bool $platform): string
    {
        if ($platform) {
            return 'core';
        }

        if (isset($meta['kind']) && is_string($meta['kind'])) {
            return $meta['kind'] === 'core' ? 'core' : 'product';
        }

        // Legacy boolean core flag (pre-rename) — treat as kind.
        if (array_key_exists('core', $meta)) {
            return (bool) $meta['core'] ? 'core' : 'product';
        }

        return 'product';
    }

    /**
     * Composition axis from raw config. Core entries never carry a parent; a
     * malformed name degrades to null rather than breaking the registry read.
     *
     * @param  array{parent?: string|null}  $meta
     */
    private function resolveParentFromMeta(array $meta, string $kind): ?string
    {
        if ($kind !== 'product') {
            return null;
        }

        $parent = $meta['parent'] ?? null;

        if (! is_string($parent) || trim($parent) === '') {
            return null;
        }

        try {
            return $this->normalizeName($parent);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function removeLinesContaining(string $path, string $needle): void
    {
        $contents = File::get($path);
        $lines = preg_split("/\r\n|\n|\r/", $contents) ?: [];
        $filtered = array_values(array_filter(
            $lines,
            static fn (string $line): bool => ! str_contains($line, $needle)
        ));

        // Rejoin faithfully: the trailing empty elements preg_split leaves behind
        // *are* the file's trailing newlines, so make → remove round-trips to the
        // same bytes. Appending an extra newline here used to add a blank line.
        $this->writeSourceFile($path, implode("\n", $filtered));
    }

    /**
     * Append a line without flattening the file's existing trailing newlines.
     */
    private function appendLine(string $contents, string $line): string
    {
        $trimmed = rtrim($contents, "\r\n");
        $suffix = substr($contents, strlen($trimmed));

        if ($suffix === '') {
            $suffix = "\n";
        }

        return $trimmed."\n".$line.$suffix;
    }

    /**
     * The only writer for a source file edited in place.
     *
     * Every mutation above anchors on the closing `];` of a PHP file and re-emits
     * it from a literal, so the file's own trailing newline is consumed and not
     * given back — `config/domains.php` and `bootstrap/providers.php` came out
     * without one, which Pint reports as `single_blank_line_at_eof` on the next
     * commit. Normalising here means no caller can produce a source file that
     * fails the style gate, and make → remove still round-trips to the same bytes
     * because the normalised suffix is what every write starts from.
     */
    private function writeSourceFile(string $path, string $contents): void
    {
        File::put($path, rtrim($contents, "\r\n")."\n");
    }

    private function providerStub(string $name): string
    {
        $short = $this->providerShortClass($name);

        return <<<PHP
<?php

namespace App\\Providers\\Domain;

use Illuminate\\Support\\ServiceProvider;

class {$short} extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}

PHP;
    }

    private function roleSeederStub(string $name): string
    {
        $name = $this->normalizeName($name);
        $short = $this->roleSeederShortClass($name);

        return <<<PHP
<?php

namespace Database\\Seeders;

use App\\Support\\DomainAccess\\Enums\\DomainVisibility;
use App\\Support\\DomainAccess\\Models\\ProductDomainVisibility;
use Database\\Seeders\\Concerns\\SeedsBetaTesterRole;
use Illuminate\\Database\\Seeder;

/**
 * RBAC + access seeder for the {$name} product domain.
 *
 * Seeds the "Beta tester {$name}" role (domain_kind = product) with its
 * "access ... product" permission and a Public visibility row so the domain
 * is reachable by default. Flip it to Private from the admin UI when ready.
 *
 * Add the domain's own H-RBAC roles/permissions below as the module grows.
 */
class {$short} extends Seeder
{
    use SeedsBetaTesterRole;

    public function run(): void
    {
        \$this->seedBetaTesterRole('{$name}');

        ProductDomainVisibility::query()->updateOrCreate(
            ['product_key' => '{$name}'],
            ['visibility' => DomainVisibility::Public->value],
        );
    }
}

PHP;
    }

    private function routeStub(string $name, string $surface): string
    {
        return <<<PHP
<?php

use Illuminate\\Support\\Facades\\Route;

/*
|--------------------------------------------------------------------------
| {$name} {$surface} routes
|--------------------------------------------------------------------------
*/

PHP;
    }
}
