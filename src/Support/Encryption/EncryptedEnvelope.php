<?php

namespace App\Support\Encryption;

use JsonException;
use RuntimeException;

readonly class EncryptedEnvelope
{
    public function __construct(
        public int $version,
        public string $algorithm,
        public string $kmsKeyId,
        public string $dekCiphertext,
        public string $iv,
        public string $tag,
        public string $ciphertext,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'v' => $this->version,
            'alg' => $this->algorithm,
            'kms_key_id' => $this->kmsKeyId,
            'dek_ciphertext' => $this->dekCiphertext,
            'iv' => $this->iv,
            'tag' => $this->tag,
            'ciphertext' => $this->ciphertext,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Envelope JSON is invalid: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($data)) {
            throw new RuntimeException('Envelope JSON must decode to an object.');
        }

        foreach (['v', 'alg', 'kms_key_id', 'dek_ciphertext', 'iv', 'tag', 'ciphertext'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new RuntimeException("Envelope JSON is missing key [{$key}].");
            }
        }

        if ((int) $data['v'] !== 1) {
            throw new RuntimeException('Unsupported envelope version.');
        }

        return new self(
            version: (int) $data['v'],
            algorithm: (string) $data['alg'],
            kmsKeyId: (string) $data['kms_key_id'],
            dekCiphertext: (string) $data['dek_ciphertext'],
            iv: (string) $data['iv'],
            tag: (string) $data['tag'],
            ciphertext: (string) $data['ciphertext'],
        );
    }

    public static function isEnvelopeJson(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            self::fromJson($value);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }
}
