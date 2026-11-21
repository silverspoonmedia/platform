<?php

namespace App\Domain\Identity\Tests\Unit\Console;

use App\Console\Commands\Domain\ListDomainsCommand;
use App\Console\Commands\EncryptionFlushDecryptedCacheCommand;
use App\Support\Testing\PackageTestCase;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

/**
 * The two console commands a consumer must inherit from the kernel.
 *
 * Both edit nothing durable — `domain:list` reads the registry and
 * `encryption:flush-decrypted-cache` forgets one cache key — so a client
 * project that installs the packages should not have to copy or re-register
 * either one. They arrive through `KernelServiceProvider::boot()`, which a
 * consumer lists in `bootstrap/providers.php`; `domain:make` / `domain:remove`
 * rewrite host trees and deliberately stay out.
 *
 * Testbench boots that provider by itself, so a green run here is the same
 * claim a mirrored tag makes: install the package, get the commands.
 */
class KernelConsoleCommandsTest extends PackageTestCase
{
    /**
     * Command name => the class the kernel owns for it.
     *
     * @var array<string, class-string>
     */
    private const KERNEL_COMMANDS = [
        'domain:list' => ListDomainsCommand::class,
        'encryption:flush-decrypted-cache' => EncryptionFlushDecryptedCacheCommand::class,
    ];

    #[Test]
    public function the_kernel_registers_both_cache_and_registry_commands(): void
    {
        /** @var array<string, Command> $registered */
        $registered = $this->app->make(ConsoleKernel::class)->all();

        foreach (self::KERNEL_COMMANDS as $name => $class) {
            $this->assertArrayHasKey(
                $name,
                $registered,
                "KernelServiceProvider::boot() must register [{$name}] for a consumer who lists only this provider.",
            );

            $this->assertInstanceOf(
                $class,
                $registered[$name],
                "[{$name}] resolves to a class the kernel does not own — a consumer would have to ship its own copy.",
            );
        }
    }

    #[Test]
    public function both_commands_ship_inside_the_platform_package(): void
    {
        foreach (self::KERNEL_COMMANDS as $name => $class) {
            $file = (new ReflectionClass($class))->getFileName();

            $this->assertIsString($file, "[{$name}] must be a real class file, not an anonymous command.");

            // `src/Console/Commands/` is the package prefix for `App\Console\`:
            // one check for the directory segment, one for this checkout's copy.
            $this->assertStringContainsString(
                DIRECTORY_SEPARATOR.'Console'.DIRECTORY_SEPARATOR.'Commands'.DIRECTORY_SEPARATOR,
                $file,
                "[{$name}] lives at [{$file}], outside the kernel's Console/Commands tree — the host must not carry the only copy.",
            );

            $this->assertStringContainsString(
                DIRECTORY_SEPARATOR.'packages'.DIRECTORY_SEPARATOR.'platform'.DIRECTORY_SEPARATOR,
                $file,
                "[{$name}] resolves out of the host's app/Console — a consumer install has no such file.",
            );
        }
    }

    #[Test]
    public function the_flush_command_is_context_scoped(): void
    {
        $definition = $this->app->make(ConsoleKernel::class)->all()['encryption:flush-decrypted-cache']->getDefinition();

        // No `--all` / `--flush` switch may exist: the cache contract is
        // driver-agnostic, so there is no bulk flush to offer.
        $this->assertTrue($definition->hasArgument('context'), 'The flush command must take a context argument.');
        $this->assertTrue(
            $definition->getArgument('context')->isRequired() === false,
            'Passing no context must reach handle() so it can fail loudly.',
        );

        $this->assertSame([], $definition->getOptions(), 'The flush command must offer no options — no bulk flush exists.');
    }
}
