<?php

namespace App\Support\Encryption;

readonly class DecryptionContext
{
    public function __construct(
        public string $resourceType,
        public ?string $resourceId,
        public string $field,
        public ?string $purpose = null,
    ) {}

    public function cacheKey(): string
    {
        $id = $this->resourceId ?? 'anonymous';

        return "{$this->resourceType}.{$this->field}.{$id}";
    }
}
