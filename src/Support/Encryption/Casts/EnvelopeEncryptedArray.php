<?php

namespace App\Support\Encryption\Casts;

use App\Support\Encryption\DecryptionContext;
use App\Support\Encryption\EncryptedEnvelope;
use App\Support\Encryption\EnvelopeEncryptionService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * @implements CastsAttributes<array<string, mixed>|null, array<string, mixed>|null>
 */
class EnvelopeEncryptedArray implements CastsAttributes
{
    public function __construct(
        private readonly string $resourceType,
        private readonly string $field,
        private readonly ?string $purpose = null,
    ) {}

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return null;
        }

        if (! EncryptedEnvelope::isEnvelopeJson($value)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);

                return is_array($decoded) ? $decoded : null;
            } catch (JsonException) {
                return null;
            }
        }

        $json = $this->encryption()->decrypt($value, $this->context($model, $key));

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JsonException('Decrypted envelope payload is not valid JSON: '.$exception->getMessage(), 0, $exception);
        }

        return is_array($decoded) ? $decoded : null;
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            $this->encryption()->decryptedValueCacheForget($this->context($model, $key));

            return null;
        }

        if (! is_array($value)) {
            throw new JsonException('Envelope encrypted array cast expects an array or null.');
        }

        $context = $this->context($model, $key);
        $json = json_encode($value, JSON_THROW_ON_ERROR);

        $existingRaw = $attributes[$key] ?? $model->getRawOriginal($key) ?? null;

        if (is_string($existingRaw) && $existingRaw !== ''
            && $this->encryption()->storedEnvelopeMatchesPlaintext($existingRaw, $context, $json)) {
            return $existingRaw;
        }

        $this->encryption()->decryptedValueCacheForget($context);

        return $this->encryption()->encrypt($json, $context);
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
}
