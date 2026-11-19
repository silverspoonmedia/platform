<?php

namespace App\Support;

class EnvironmentSwitchResult
{
    public function __construct(
        public readonly ?string $previousProfile,
        public readonly string $activatedProfile,
        public readonly bool $bootstrapped,
        public readonly bool $skipped,
    ) {}
}
