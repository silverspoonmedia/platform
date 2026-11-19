<?php

namespace App\Support\Encryption\Contracts;

use App\Support\Encryption\EnvelopeEncryptionService;

/**
 * Kernel-owned seam for the `encryption:migrate-p0` sweeper.
 *
 * The kernel command knows how to migrate its own columns (`users`,
 * `cache`) but a packaged domain owns the column it encrypts. Registering
 * a migrator keeps the command from importing a package model, and keeps the
 * migration list honest: an unregistered table simply is not swept.
 */
interface LegacyColumnMigrator
{
    /**
     * Label printed by the command (`invoices.gateway_request_payload`).
     */
    public function key(): string;

    /**
     * Convert every legacy ciphertext in this column to an envelope.
     *
     * @return int Rows migrated.
     */
    public function migrate(EnvelopeEncryptionService $encryption): int;
}
