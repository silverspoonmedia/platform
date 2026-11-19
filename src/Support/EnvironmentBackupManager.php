<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Creates, commits, rolls back, and lists transactional backups of env files.
 */
class EnvironmentBackupManager
{
    private const PENDING_DIR = 'framework/env-backups/pending';

    public function __construct(
        private readonly ?string $storagePath = null,
        private readonly ?string $basePath = null,
    ) {}

    public function hasPending(): bool
    {
        return $this->latestPending() !== null;
    }

    public function latestPending(): ?PendingBackup
    {
        $pending = $this->listPending();

        if ($pending === []) {
            return null;
        }

        return $pending[array_key_last($pending)];
    }

    /**
     * @return array<int, PendingBackup>
     */
    public function listPending(): array
    {
        $root = $this->pendingRoot();

        if (! File::isDirectory($root)) {
            return [];
        }

        $backups = [];

        foreach (File::directories($root) as $directory) {
            $metaPath = $directory.DIRECTORY_SEPARATOR.'meta.json';

            if (! File::exists($metaPath)) {
                continue;
            }

            $meta = json_decode(File::get($metaPath), true);

            if (! is_array($meta)) {
                continue;
            }

            $backups[] = new PendingBackup(
                id: basename($directory),
                operation: (string) ($meta['operation'] ?? 'unknown'),
                directory: $directory,
                createdAt: (int) ($meta['created_at'] ?? 0),
                files: is_array($meta['files'] ?? null) ? $meta['files'] : [],
            );
        }

        usort(
            $backups,
            fn (PendingBackup $a, PendingBackup $b): int => $a->createdAt <=> $b->createdAt,
        );

        return $backups;
    }

    /**
     * @param  array<string, string>  $paths  absolute source path => relative label for restore (e.g. ".env")
     */
    public function createPending(string $operation, array $paths): PendingBackup
    {
        if ($paths === []) {
            throw new RuntimeException('Cannot create backup — no files to back up.');
        }

        $safeOperation = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $operation);
        $id = trim($safeOperation, '-').'-'.time();
        $directory = $this->pendingRoot().DIRECTORY_SEPARATOR.$id;
        $filesDir = $directory.DIRECTORY_SEPARATOR.'files';

        File::makeDirectory($filesDir, recursive: true);

        $fileMeta = [];

        foreach ($paths as $absolutePath => $label) {
            if (! File::exists($absolutePath)) {
                continue;
            }

            $backupRelative = 'files'.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $label);
            $backupAbsolute = $directory.DIRECTORY_SEPARATOR.$backupRelative;

            $parent = dirname($backupAbsolute);

            if (! File::isDirectory($parent)) {
                File::makeDirectory($parent, recursive: true);
            }

            File::copy($absolutePath, $backupAbsolute);

            $fileMeta[] = [
                'original' => $label,
                'backup' => $backupRelative,
            ];
        }

        if ($fileMeta === []) {
            File::deleteDirectory($directory);

            throw new RuntimeException('Cannot create backup — none of the specified files exist.');
        }

        $createdAt = time();

        File::put($directory.DIRECTORY_SEPARATOR.'meta.json', json_encode([
            'operation' => $operation,
            'created_at' => $createdAt,
            'files' => $fileMeta,
        ], JSON_PRETTY_PRINT).PHP_EOL);

        return new PendingBackup(
            id: $id,
            operation: $operation,
            directory: $directory,
            createdAt: $createdAt,
            files: $fileMeta,
        );
    }

    public function commit(PendingBackup $backup): void
    {
        if (File::isDirectory($backup->directory)) {
            File::deleteDirectory($backup->directory);
        }
    }

    public function rollback(PendingBackup $backup): void
    {
        foreach ($backup->files as $file) {
            $original = $this->resolveBasePath($file['original']);
            $backupFile = $backup->directory.DIRECTORY_SEPARATOR.str_replace(
                ['/', '\\'],
                DIRECTORY_SEPARATOR,
                $file['backup'],
            );

            if (! File::exists($backupFile)) {
                continue;
            }

            File::copy($backupFile, $original);
        }
    }

    public function discard(PendingBackup $backup): void
    {
        $this->commit($backup);
    }

    public function findById(string $id): ?PendingBackup
    {
        foreach ($this->listPending() as $backup) {
            if ($backup->id === $id) {
                return $backup;
            }
        }

        return null;
    }

    public function pendingRoot(): string
    {
        $storage = $this->storagePath ?? storage_path();

        return rtrim($storage, '/\\').DIRECTORY_SEPARATOR.self::PENDING_DIR;
    }

    private function resolveBasePath(string $relative): string
    {
        $root = $this->basePath ?? base_path();

        return rtrim($root, '/\\').DIRECTORY_SEPARATOR.ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative), DIRECTORY_SEPARATOR);
    }
}
