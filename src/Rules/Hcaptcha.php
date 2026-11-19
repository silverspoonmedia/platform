<?php

namespace App\Rules;

use App\Support\Security\HcaptchaVerifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Hcaptcha implements ValidationRule
{
    public function __construct(
        private readonly HcaptchaVerifier $verifier,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->verifier->verify((string) $value, request()->ip())) {
            $fail(__('validation.hcaptcha'));
        }
    }
}
