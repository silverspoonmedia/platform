<?php

namespace App\Support\Encryption;

use App\Support\Oci\Kms\Contracts\KmsDekClientInterface;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

class EnvelopeEncryptionService
{
    private const ALGORITHM = 'AES-256-GCM';

    public function __construct(
        private readonly KmsDekClientInterface $kmsClient,
        private readonly DecryptedValueCache $decryptedValueCache,
        private readonly ?DecryptionAuditRecorder $cryptoActivityRecorder = null,
        private readonly InvoicePayloadDecryptLogger $invoicePayloadDecryptLogger = new InvoicePayloadDecryptLogger,
    ) {}

    public function encrypt(string $plaintext, ?DecryptionContext $context = null): string
    {
        if ($context !== null) {
            $this->decryptedValueCache->forgetByContext($context->cacheKey());
        }

        $dekResult = $this->kmsClient->generateDek();

        if (! $dekResult->successful || $dekResult->plaintextDek === null || $dekResult->wrappedDek === null) {
            throw new RuntimeException($dekResult->message ?? 'Failed to generate a data encryption key.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $dekResult->plaintextDek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed.');
        }

        $envelope = new EncryptedEnvelope(
            version: 1,
            algorithm: self::ALGORITHM,
            kmsKeyId: (string) config('oci.kms_key_id', 'local'),
            dekCiphertext: $dekResult->wrappedDek,
            iv: base64_encode($iv),
            tag: base64_encode($tag),
            ciphertext: base64_encode($ciphertext),
        );

        return $envelope->toJson();
    }

    public function decrypt(string $storedValue, DecryptionContext $context, string $operation = 'read'): string
    {
        $contextKey = $context->cacheKey();
        $cacheHit = $this->decryptedValueCache->has($contextKey, $storedValue);

        $plaintext = $this->decryptedValueCache->remember(
            $contextKey,
            $storedValue,
            fn (): string => $this->decryptWithoutCache($storedValue),
            $this->resolveDecryptCacheTtl($context),
        );

        $this->invoicePayloadDecryptLogger->log($context, $cacheHit, $operation);

        if ($this->shouldAuditDecrypt($cacheHit, $context) && $this->cryptoActivityRecorder !== null) {
            $metadata = $this->resolveAuditMetadata($storedValue);

            $this->cryptoActivityRecorder->recordFieldDecrypted(
                resourceType: $context->resourceType,
                resourceId: $context->resourceId,
                field: $context->field,
                purpose: $context->purpose,
                kmsKeyId: $metadata['kms_key_id'],
                cacheHit: $cacheHit,
                envelopeVersion: $metadata['envelope_version'],
                legacyCiphertext: $metadata['legacy_ciphertext'],
            );
        }

        return $plaintext;
    }

    public function decryptWithoutCache(string $storedValue): string
    {
        if (EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return $this->decryptEnvelope($storedValue);
        }

        return $this->decryptLegacyValue($storedValue);
    }

    public function migrateLegacyValue(string $storedValue): string
    {
        if (EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return $storedValue;
        }

        $plaintext = $this->decryptLegacyValue($storedValue);

        return $this->encrypt($plaintext);
    }

    public function decryptedValueCacheForget(DecryptionContext $context): void
    {
        $this->decryptedValueCache->forgetByContext($context->cacheKey());
    }

    public function storedEnvelopeMatchesPlaintext(
        string $storedValue,
        DecryptionContext $context,
        string $plaintext,
    ): bool {
        if (! EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return false;
        }

        return hash_equals($plaintext, $this->decrypt($storedValue, $context, 'compare'));
    }

    private function decryptEnvelope(string $storedValue): string
    {
        $envelope = EncryptedEnvelope::fromJson($storedValue);

        $dekResult = $this->kmsClient->decryptDek($envelope->dekCiphertext);

        if (! $dekResult->successful || $dekResult->plaintextDek === null) {
            throw new RuntimeException($dekResult->message ?? 'Failed to unwrap the data encryption key.');
        }

        $iv = base64_decode($envelope->iv, true);
        $tag = base64_decode($envelope->tag, true);
        $ciphertext = base64_decode($envelope->ciphertext, true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            throw new RuntimeException('Envelope payload contains invalid base64.');
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $dekResult->plaintextDek,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if ($plaintext === false) {
            throw new RuntimeException('AES-256-GCM decryption failed.');
        }

        return $plaintext;
    }

    private function decryptLegacyValue(string $storedValue): string
    {
        try {
            return Crypt::decryptString($storedValue);
        } catch (\Throwable) {
            return $storedValue;
        }
    }

    /**
     * Per-resource_type TTL from config map; else global decrypted_cache_max_ttl.
     * 0 = forever until forget.
     */
    public function resolveDecryptCacheTtl(DecryptionContext $context): int
    {
        $byType = (array) config('encryption.decrypted_cache_ttl_by_resource_type', []);
        $resourceType = $context->resourceType;

        if (array_key_exists($resourceType, $byType)) {
            return (int) $byType[$resourceType];
        }

        return (int) config('encryption.decrypted_cache_max_ttl', 86400);
    }

    private function shouldAuditDecrypt(bool $cacheHit, DecryptionContext $context): bool
    {
        if ($this->isAuditExcluded($context)) {
            return false;
        }

        if (config('encryption.audit_decrypts') === 'always') {
            return true;
        }

        return ! $cacheHit;
    }

    private function isAuditExcluded(DecryptionContext $context): bool
    {
        $excludedTypes = (array) config('encryption.audit_excluded_resource_types', []);
        $excludedPurposes = (array) config('encryption.audit_excluded_purposes', []);

        return in_array($context->resourceType, $excludedTypes, true)
            || ($context->purpose !== null && in_array($context->purpose, $excludedPurposes, true));
    }

    /**
     * @return array{kms_key_id: ?string, envelope_version: ?int, legacy_ciphertext: bool}
     */
    private function resolveAuditMetadata(string $storedValue): array
    {
        if (! EncryptedEnvelope::isEnvelopeJson($storedValue)) {
            return [
                'kms_key_id' => null,
                'envelope_version' => null,
                'legacy_ciphertext' => true,
            ];
        }

        $envelope = EncryptedEnvelope::fromJson($storedValue);

        return [
            'kms_key_id' => $envelope->kmsKeyId,
            'envelope_version' => $envelope->version,
            'legacy_ciphertext' => false,
        ];
    }
}
