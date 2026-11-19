<?php

namespace App\Domain\Identity\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithListDisplayMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexUserRequest extends FormRequest
{
    use InteractsWithListDisplayMode;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->listDisplayModeRules();
    }
}
