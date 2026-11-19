<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use App\Domain\Identity\Http\Requests\Auth\Concerns\ValidatesHcaptcha;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember_me' => ['sometimes', 'boolean'],
        ], $this->hcaptchaRules());
    }
}
