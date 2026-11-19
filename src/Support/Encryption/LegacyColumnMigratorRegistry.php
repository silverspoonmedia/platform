<?php

namespace App\Support\Encryption;

use App\Support\Encryption\Contracts\LegacyColumnMigrator;

/**
 * Kernel-owned list of package-supplied P0 column migrators.
 *
 * Stores class strings, not instances: the sweeper command is the only reader,
 * and it resolves each one at run time (Octane-safe, and the command still
 * boots in a host with no package registered).
 */
final class LegacyColumnMigratorRegistry
{
    /** @var array<string, class-string<LegacyColumnMigrator>> */
    private array $migrators = [];

    /**
     * @param  class-string<LegacyColumnMigrator>  $migrator
     */
    public function register(string $migrator): void
    {
        $this->migrators[$migrator] = $migrator;
    }

    /**
     * @return list<LegacyColumnMigrator>
     */
    public function all(): array
    {
        return array_values(array_map(
            static fn (string $class): LegacyColumnMigrator => app($class),
            array_values($this->migrators),
        ));
    }
}
