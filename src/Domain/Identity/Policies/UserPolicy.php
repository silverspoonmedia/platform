<?php

namespace App\Domain\Identity\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    use HandlesAuthorization;

    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    public function viewAny(User $actor): Response
    {
        return $this->checkPermission($actor, 'view user');
    }

    public function view(User $actor, User $target): Response
    {
        if ($actor->is($target)) {
            return $this->allow();
        }

        $permission = $this->checkPermission($actor, 'view user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkHierarchy($actor, $target);
    }

    public function create(User $actor): Response
    {
        return $this->checkPermission($actor, 'create user');
    }

    public function update(User $actor, User $target): Response
    {
        $permission = $this->checkPermission($actor, 'edit user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkAllRolesAbove($actor, $target);
    }

    public function delete(User $actor, User $target): Response
    {
        $permission = $this->checkPermission($actor, 'delete user');

        if ($permission->denied()) {
            return $permission;
        }

        return $this->checkAllRolesAbove($actor, $target);
    }

    public function assignableRoles(User $actor): Response
    {
        if ($actor->can('create user') || $actor->can('edit user')) {
            return $this->allow();
        }

        return $this->deny(__('api/users.policy.assignable_roles'));
    }

    private function checkPermission(User $actor, string $permission): Response
    {
        if ($actor->can($permission)) {
            return $this->allow();
        }

        $message = match ($permission) {
            'view user' => __('api/users.policy.view'),
            'create user' => __('api/users.policy.create'),
            'edit user' => __('api/users.policy.edit'),
            'delete user' => __('api/users.policy.delete'),
            default => __('api/users.policy.default'),
        };

        return $this->deny($message);
    }

    private function checkHierarchy(User $actor, User $target): Response
    {
        if ($this->roleService->canActorViewTarget($actor, $target)) {
            return $this->allow();
        }

        return $this->deny(__('api/users.policy.hierarchy_view'));
    }

    private function checkAllRolesAbove(User $actor, User $target): Response
    {
        if ($this->roleService->allTargetRolesAboveActor($actor, $target)) {
            return $this->allow();
        }

        return $this->deny(__('api/users.policy.hierarchy_modify'));
    }
}
