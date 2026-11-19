<?php

namespace App\Domain\Identity\Http\Requests\Lookup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'ids' => ['sometimes', 'array'],
            'ids.*' => ['uuid'],
        ];
    }

    public function searchTerm(): ?string
    {
        $q = $this->validated('q');

        if (! is_string($q)) {
            return null;
        }

        $trimmed = trim($q);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        $ids = $this->validated('ids');

        return is_array($ids) ? array_values($ids) : [];
    }

    public function page(): int
    {
        return max(1, (int) $this->validated('page', 1));
    }

    public function perPage(): int
    {
        return min(50, max(1, (int) $this->validated('per_page', config('lookup.per_page', 10))));
    }
}
