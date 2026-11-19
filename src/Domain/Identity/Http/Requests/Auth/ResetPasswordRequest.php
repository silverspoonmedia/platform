<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use App\Domain\Identity\Http\Requests\Auth\Concerns\ValidatesHcaptcha;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], $this->hcaptchaRules());
    }
}
