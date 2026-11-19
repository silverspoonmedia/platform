<?php

namespace App\Support\DomainAccess\Http\Requests;

use App\Support\DomainAccess\Enums\DomainVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateDomainVisibilityRequest extends FormRequest
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
            'visibility' => ['required', new Enum(DomainVisibility::class)],
        ];
    }

    public function visibility(): DomainVisibility
    {
        return DomainVisibility::from((string) $this->validated('visibility'));
    }
}
