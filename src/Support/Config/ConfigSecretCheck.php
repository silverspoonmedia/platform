<?php

namespace App\Support\Config;

readonly class ConfigSecretCheck
{
    public function __construct(
        public string $key,
        public string $label,
        public string $status,
        public string $severity = 'error',
    ) {}

    public function isError(): bool
    {
        return $this->severity === 'error' && $this->status !== 'ok';
    }

    public function isWarning(): bool
    {
        return $this->severity === 'warning' && $this->status !== 'ok';
    }
}
