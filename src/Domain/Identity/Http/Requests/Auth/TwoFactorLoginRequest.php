<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use App\Domain\Identity\Http\Requests\Auth\Concerns\ValidatesHcaptcha;
use Illuminate\Foundation\Http\FormRequest;

class TwoFactorLoginRequest extends FormRequest
{
    use ValidatesHcaptcha;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'two_factor_token' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ], $this->hcaptchaRules());
    }
}
