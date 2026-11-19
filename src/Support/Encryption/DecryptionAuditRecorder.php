<?php

namespace App\Support\Encryption;

/**
 * Kernel-owned contract for auditing envelope decryptions.
 *
 * `EnvelopeEncryptionService` is the sole decrypt choke point and must not
 * import the audit domain that stores the `crypto` trail — the kernel declares
 * what it needs, `silverspoonmedia/audit` binds the implementation. A host without
 * that package still boots: the dependency is optional, so the container
 * resolves the `null` default instead of failing.
 */
interface DecryptionAuditRecorder
{
    /**
     * Record one decryption that passed the `encryption.audit_decrypts` filter.
     */
    public function recordFieldDecrypted(
        string $resourceType,
        ?string $resourceId,
        string $field,
        ?string $purpose = null,
        ?string $kmsKeyId = null,
        ?bool $cacheHit = null,
        ?int $envelopeVersion = null,
        ?bool $legacyCiphertext = null,
        array $extraProperties = [],
    ): void;
}
