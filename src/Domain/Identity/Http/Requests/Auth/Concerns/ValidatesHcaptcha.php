<?php

namespace App\Domain\Identity\Http\Requests\Auth\Concerns;

use App\Rules\Hcaptcha;

trait ValidatesHcaptcha
{
    /**
     * @return array<string, mixed>
     */
    protected function hcaptchaRules(): array
    {
        if (! setting_config('hcaptcha.enabled')) {
            return [];
        }

        return [
            'h-captcha-response' => ['required', 'string', app(Hcaptcha::class)],
        ];
    }
}
