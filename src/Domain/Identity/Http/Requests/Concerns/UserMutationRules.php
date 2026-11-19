<?php

namespace App\Domain\Identity\Http\Requests\Concerns;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use Illuminate\Validation\Rule;

trait UserMutationRules
{
    /**
     * @return list<string>
     */
    protected function assignableRoleNames(): array
    {
        /** @var RoleService $roleService */
        $roleService = app(RoleService::class);

        return $roleService->getAssignableRoles($this->user())->pluck('name')->all();
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function userStoreRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'role' => ['required', 'string', Rule::in($this->assignableRoleNames())],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function userUpdateRules(User $target): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($target->id),
            ],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'password_confirmation' => ['required_with:password', 'string', 'same:password'],
            'role' => ['sometimes', 'required', 'string', Rule::in($this->assignableRoleNames())],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function userRoleMessages(): array
    {
        return [
            'role.in' => __('forms/users.role_not_assignable'),
        ];
    }
}
