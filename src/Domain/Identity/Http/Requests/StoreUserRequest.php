<?php

namespace App\Domain\Identity\Http\Requests;

use App\Domain\Identity\Http\Requests\Concerns\UserMutationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    use UserMutationRules;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->userStoreRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->userRoleMessages();
    }
}
