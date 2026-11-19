<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscordAuthorizeUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intent' => ['required', 'string', Rule::in([
                'profile',
            ])],
            'return_path' => ['required', 'string', 'max:512'],
        ];
    }
}
