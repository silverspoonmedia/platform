<?php

namespace App\Support\Oci;

readonly class OciConfig
{
    public function __construct(
        public string $tenancy,
        public string $user,
        public string $fingerprint,
        public string $privateKeyPath,
        public string $region,
    ) {}

    public function keyId(): string
    {
        return "{$this->tenancy}/{$this->user}/{$this->fingerprint}";
    }
}
