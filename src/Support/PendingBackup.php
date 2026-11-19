<?php

namespace App\Support;

/**
 * Metadata for a transactional env-file backup under storage/framework/env-backups/pending/.
 */
final class PendingBackup
{
    /**
     * @param  array<int, array{original: string, backup: string}>  $files  original relative path => backup path relative to storage root
     */
    public function __construct(
        public readonly string $id,
        public readonly string $operation,
        public readonly string $directory,
        public readonly int $createdAt,
        public readonly array $files,
    ) {}

    public function metaPath(): string
    {
        return $this->directory.DIRECTORY_SEPARATOR.'meta.json';
    }
}
