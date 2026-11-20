<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Support\Domain\DomainManager;
use App\Support\Testing\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * What a kernel-only install owes its own source: every `config()` literal in
 * `packages/platform/src` must resolve.
 *
 * The monolith proves this through `KernelConfigParityTest`, which compares the
 * package config against the host copy. A consumer has no host copy, so that
 * check proves nothing there — this one asks the question a consumer actually
 * has. Two groups, two assertions:
 *
 * - **Kernel-owned** (`platform`, `spa-routes`, `localization`, `services`,
 *   `hcaptcha`, `products`, `domains`, `app`, `auth`, …): the provider must ship
 *   it, so the value is non-null on Testbench alone. `KernelServiceProvider`
 *   merges those five files; Testbench's skeleton supplies the framework groups.
 * - **Host-published** (`archivd`, `notifications`, `oci`, `filesystems`,
 *   `lookup`): read from a package but owned by the project — an upload proxy,
 *   a notification channel, an OCI profile, a disk. The kernel cannot invent
 *   them, so the assertion is that the consumer template publishes the file
 *   (`client-scaffold/config/{group}.php`). A consumer who installs only the
 *   packages reads a default, never a fatal.
 *
 * `KernelConfigParityTest` stays as the monolith guard; this is the consumer
 * side of the same invariant.
 */
class KernelConfigSelfSufficiencyTest extends PackageTestCase
{
    /**
     * Groups the kernel must resolve with no host config directory at all.
     *
     * @var list<string>
     */
    private const KERNEL_OWNED = [
        'platform',
        'spa-routes',
        'localization',
        'services',
        'hcaptcha',
    ];

    /**
     * Registry groups: the kernel reads them, the consumer project declares them.
     *
     * `domains` is the module registry (`config/domains.php`), which mounts the
     * packages — the scaffold ships it, since a route loader with no registry
     * mounts nothing. `products` is the activation catalog; a consumer with no
     * product domain installed legitimately leaves it out, and
     * `ProductRegistry::all()` answers `[]` (an EMPTY registry is a legal state).
     *
     * @var list<string>
     */
    private const REGISTRY = [
        'domains',
        'products',
    ];

    /**
     * Groups a consumer project publishes itself, and the scaffold proves it.
     *
     * @var list<string>
     */
    private const HOST_PUBLISHED = [
        'app',
        'auth',
        'broadcasting',
        'cors',
        'database',
        'encryption',
        'filesystems',
        'jwt',
        'lookup',
        'mail',
        'oci',
        'permission',
        'session',
    ];

    /**
     * Groups a **package** merges, so the install that reads them carries them.
     *
     * `notifications` is merged by `silverspoonmedia/notification`, which is in
     * the scaffold's `require`; the kernel only reads the delivery flag.
     *
     * @var list<string>
     */
    private const PACKAGE_MERGED = [
        'notifications',
    ];

    /**
     * Groups a **product** owns, read only behind a default.
     *
     * `S3DirectUploadEngine` asks for `archivd.upload_proxy.*` and
     * `archivd.debug_log_requests` to decide whether an upload proxy is
     * configured. A consumer with no product installed reads the defaults and
     * takes the direct path, so the key is legal and never load-bearing.
     *
     * @var list<string>
     */
    private const PRODUCT_OWNED = [
        'archivd',
    ];

    #[Test]
    public function every_kernel_config_group_resolves_without_a_host_config_directory(): void
    {
        foreach (self::KERNEL_OWNED as $group) {
            $this->assertNotSame(
                null,
                $this->app['config']->get($group),
                "The kernel provider does not merge [{$group}] — a package-only install reads null for every key in it.",
            );
        }

        // The five files the provider merges: assert the file, not just the value,
        // because a framework default could mask a missing merge for `services`.
        foreach (self::KERNEL_OWNED as $group) {
            $this->assertFileExists(
                $this->packageRoot().'/config/'.$group.'.php',
                "KernelServiceProvider::mergeConfigFrom expects config/{$group}.php beside the provider.",
            );
        }
    }

    #[Test]
    public function every_config_literal_the_kernel_source_reads_belongs_to_a_declared_group(): void
    {
        $reported = [];

        foreach ($this->literalConfigKeys() as $key => $files) {
            $group = explode('.', $key, 2)[0];

            if (in_array($group, self::KERNEL_OWNED, true)
                || in_array($group, self::REGISTRY, true)
                || in_array($group, self::HOST_PUBLISHED, true)
                || in_array($group, self::PACKAGE_MERGED, true)
                || in_array($group, self::PRODUCT_OWNED, true)) {
                continue;
            }

            // A sourced key is one the kernel reads; a group neither the kernel
            // ships nor the scaffold publishes is a consumer install waiting to
            // read null.
            $reported[] = $group.' ('.implode(', ', array_slice($files, 0, 3)).')';
        }

        $this->assertSame([], $reported, 'These config groups are read by packages/platform/src but declared neither in KERNEL_OWNED nor HOST_PUBLISHED: '.implode(' · ', $reported));
    }

    #[Test]
    public function every_host_published_group_is_provided_by_the_consumer_template(): void
    {
        $scaffold = $this->scaffoldConfigDirectory();

        // A cloned mirror ships only this package: there is no consumer template
        // to compare against, and skipping is the honest answer — a green run
        // there cannot claim the scaffold was checked.
        if ($scaffold === null) {
            $this->markTestSkipped('client-scaffold/ is not beside this checkout (a mirror clone): the consumer template is unverifiable here.');
        }

        foreach (self::HOST_PUBLISHED as $group) {
            $this->assertFileExists(
                $scaffold.'/'.$group.'.php',
                "A consumer install reads config/{$group}.php and the kernel cannot ship it — client-scaffold/config/{$group}.php must carry it, or the first request that uses it resolves null.",
            );
        }
    }

    #[Test]
    public function the_route_registry_names_every_core_domain_package(): void
    {
        // The registry the kernel ships (`config/domains.php` of the monolith)
        // read off disk: Testbench boots a skeleton with no registry at all, so a
        // `config('domains')` assertion there would prove nothing. A mirror clone
        // has no registry beside the package — nothing to prove, and skipping
        // beats asserting against a file that is not there.
        $registry = $this->repoRoot().'/config/domains.php';

        if (! is_file($registry)) {
            $this->markTestSkipped('config/domains.php is not beside this checkout (a mirror clone): the route registry is unverifiable here.');
        }

        /** @var array<string, array<string, mixed>> $domains */
        $domains = (array) require $registry;

        $core = [];

        foreach ($domains as $name => $meta) {
            if (($meta['kind'] ?? null) === 'core') {
                $core[] = $name;
            }
        }

        $this->assertSame(
            DomainManager::PLATFORM_CORE,
            $core,
            'Every core domain must sit in the registry, in PLATFORM_CORE order — that order is route load order.',
        );

        foreach ($core as $name) {
            $meta = $domains[$name];

            $this->assertArrayHasKey('package', $meta, "Kernel domain [{$name}] must name its Composer package so a consumer install resolves it.");
            $this->assertArrayHasKey('routes', $meta, "Kernel domain [{$name}] must name its route file so a consumer can mount it.");
        }
    }

    /**
     * Absolute directory of `silverspoonmedia/platform`, wherever it is installed.
     *
     * `__FILE__` is the real package tree even when the autoloader reached it
     * through a junction, so this works in the monolith and in a consumer's
     * `vendor/silverspoonmedia/platform` alike.
     */
    private function packageRoot(): string
    {
        return dirname(__DIR__, 4);
    }

    /**
     * Repository root of the checkout hosting the suite, or the package root when
     * a cloned mirror has no monolith beside it.
     */
    private function repoRoot(): string
    {
        $root = dirname(__DIR__, 6);

        return is_dir($root.'/client-scaffold') ? $root : $this->packageRoot();
    }

    /**
     * `client-scaffold/config` of the checkout hosting the suite, if any.
     */
    private function scaffoldConfigDirectory(): ?string
    {
        $directory = $this->repoRoot().'/client-scaffold/config';

        return is_dir($directory) ? $directory : null;
    }

    /**
     * Every literal `config('group.key')` in the kernel source, keyed by the full
     * dotted path, mapped to the files that read it.
     *
     * Assembled keys (`config('domains.'.$name)`) are out of scope: their prefix
     * is the contract, and the prefix is what this test checks.
     *
     * @return array<string, list<string>>
     */
    private function literalConfigKeys(): array
    {
        $root = $this->packageRoot().'/src';
        $keys = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            preg_match_all("/config\\('([A-Za-z0-9_.]+)'/", $source, $matches);

            foreach ($matches[1] as $key) {
                // A trailing dot is the prefix of an assembled read; drop it.
                $key = rtrim($key, '.');

                $keys[$key][] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }

        return $keys;
    }
}
