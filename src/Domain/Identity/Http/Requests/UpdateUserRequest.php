<?php

namespace App\Domain\Identity\Http\Requests;

use App\Domain\Identity\Http\Requests\Concerns\UserMutationRules;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
        /** @var User $target */
        $target = $this->route('user');

        return $this->userUpdateRules($target);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->userRoleMessages();
    }
}
