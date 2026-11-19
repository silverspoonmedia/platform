<?php

namespace App\Domain\Identity\Http\Requests\Auth;

use App\Support\Localization\LocalePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(LocalePreference::accepted())],
        ];
    }
}
