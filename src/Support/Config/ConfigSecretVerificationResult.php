<?php

namespace App\Support\Config;

readonly class ConfigSecretVerificationResult
{
    /**
     * @param  list<ConfigSecretCheck>  $checks
     */
    public function __construct(
        public array $checks,
        public bool $fromFreshBootstrap = false,
    ) {}

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->isError()) {
                return false;
            }
        }

        return true;
    }

    public function errorCount(): int
    {
        return count(array_filter($this->checks, fn (ConfigSecretCheck $check): bool => $check->isError()));
    }

    public function warningCount(): int
    {
        return count(array_filter($this->checks, fn (ConfigSecretCheck $check): bool => $check->isWarning()));
    }
}
