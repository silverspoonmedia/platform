<?php

namespace App\Support;

use RuntimeException;
use Throwable;

/**
 * Wraps env mutations in backup → mutate → commit/rollback.
 */
class EnvironmentMutationGuard
{
    public function __construct(
        private readonly EnvironmentBackupManager $backups,
    ) {}

    /**
     * @param  array<string, string>  $backupPaths  absolute path => relative label (e.g. ".env")
     */
    public function run(
        string $operation,
        callable $mutate,
        array $backupPaths,
        bool $forcePastBackup = false,
    ): mixed {
        if ($this->backups->hasPending() && ! $forcePastBackup) {
            $pending = $this->backups->latestPending();

            throw new RuntimeException(
                'A pending env backup exists ['.$pending->id.']. '
                .'Run `php artisan env:restore` or `php artisan env:restore --discard` before continuing.',
            );
        }

        if ($backupPaths === []) {
            return $mutate();
        }

        $pending = $this->backups->createPending($operation, $backupPaths);

        try {
            $result = $mutate();
            $this->backups->commit($pending);

            return $result;
        } catch (Throwable $exception) {
            $this->backups->rollback($pending);

            throw new RuntimeException(
                $exception->getMessage().' Env files were rolled back. Backup kept at: '.$pending->directory,
                previous: $exception,
            );
        }
    }
}
