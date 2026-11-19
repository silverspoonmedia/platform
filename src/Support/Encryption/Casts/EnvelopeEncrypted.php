<?php

namespace App\Support\Encryption\Casts;

use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * @implements CastsAttributes<string|null, string|null>
 */
class EnvelopeEncrypted implements CastsAttributes
{
    public function __construct(
        private readonly string $resourceType,
        private readonly string $field,
        private readonly ?string $purpose = null,
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! EncryptedEnvelope::isEnvelopeJson($value) && ! $this->looksLikeLegacyCrypt($value)) {
            return $value;
        }

        return $this->encryption()->decrypt(
            $value,
            $this->context($model, $key),
        );
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            $this->encryption()->decryptedValueCacheForget($this->context($model, $key));

            return null;
        }

        if (EncryptedEnvelope::isEnvelopeJson((string) $value)) {
            return (string) $value;
        }

        $context = $this->context($model, $key);
        $plaintext = (string) $value;

        $existingRaw = $attributes[$key] ?? $model->getRawOriginal($key) ?? null;

        if (is_string($existingRaw) && $existingRaw !== ''
            && $this->encryption()->storedEnvelopeMatchesPlaintext($existingRaw, $context, $plaintext)) {
            return $existingRaw;
        }

        $this->encryption()->decryptedValueCacheForget($context);

        return $this->encryption()->encrypt($plaintext, $context);
    }

    private function context(Model $model, string $key): DecryptionContext
    {
        $resourceId = $model->getKey();

        return new DecryptionContext(
            resourceType: $this->resourceType,
            resourceId: is_scalar($resourceId) ? (string) $resourceId : null,
            field: $this->field !== '' ? $this->field : $key,
            purpose: $this->purpose,
        );
    }

    private function encryption(): EnvelopeEncryptionService
    {
        return app(EnvelopeEncryptionService::class);
    }

    private function looksLikeLegacyCrypt(string $value): bool
    {
        return str_starts_with($value, 'eyJpdiI6');
    }
}
