<?php

namespace App\Support\Encryption\Cache;

use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use JsonException;

class SensitiveCacheValue
{
    public function __construct(
        private readonly EnvelopeEncryptionService $encryption,
    ) {}

    public function isSensitiveKey(string $key): bool
    {
        foreach ((array) config('encryption.sensitive_cache_prefixes', []) as $prefix) {
            if (str_starts_with($key, (string) $prefix)) {
                return true;
            }
        }

        return false;
    }

    public function wrap(string $key, mixed $value): mixed
    {
        if (! $this->isSensitiveKey($key) || ! is_array($value)) {
            return $value;
        }

        $json = json_encode($value, JSON_THROW_ON_ERROR);

        return $this->encryption->encrypt($json, $this->context($key));
    }

    public function unwrap(string $key, mixed $value): mixed
    {
        if (! $this->isSensitiveKey($key) || ! is_string($value)) {
            return $value;
        }

        if (! EncryptedEnvelope::isEnvelopeJson($value)) {
            return is_array($value) ? $value : json_decode($value, true);
        }

        $json = $this->encryption->decrypt($value, $this->context($key));

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    private function context(string $key): DecryptionContext
    {
        return new DecryptionContext(
            resourceType: 'cache',
            resourceId: $key,
            field: 'value',
            purpose: 'sensitive_cache',
        );
    }
}
