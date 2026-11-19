<?php

namespace App\Domain\Identity\Services\User;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Services\Rbac\RoleService;
use App\Domain\Identity\Support\RbacRealtime;
use App\Support\ListActions\NavigationTreeFlusher;
use App\Support\Notifications\Contracts\NotificationDispatcher;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator as LengthAwarePaginatorConcrete;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleService $roleService,
        private readonly RbacRealtime $realtime,
    ) {}

    /**
     * @return Collection<int, User>
     */
    public function list(User $actor): Collection
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return new Collection;
        }

        return $this->users->listVisibleToActorLevel($actorLevel);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(User $actor, int $perPage, int $page): LengthAwarePaginator
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return new LengthAwarePaginatorConcrete([], 0, $perPage, $page);
        }

        return $this->users->paginateVisibleToActorLevel($actorLevel, $perPage, $page);
    }

    public function get(string $id): User
    {
        return $this->users->findByIdOrFailWithRoles($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): User
    {
        $roleName = $data['role'];
        $this->assertRoleAssignable($actor, $roleName);

        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $user->syncRoles([$roleName]);

        if (app()->bound(NavigationTreeFlusher::class)) {
            app(NavigationTreeFlusher::class)
                ->forgetPresentedTreeForUserId((string) $user->id);
        }

        $this->realtime->changed('role_assigned', null, (string) $user->id);
        $this->realtime->userListChanged('created', (string) $user->id);

        activity()
            ->useLog('user')
            ->causedBy($actor)
            ->performedOn($user)
            ->event('role_assigned')
            ->withProperties(['role' => $roleName])
            ->log('role_assigned');

        // Fan out a role-targeted notification (Root + Admin by default). Both the
        // API and the Blade UI funnel user creation through here, so one dispatch
        // covers every entry point.
        $this->notify(
            'user_created',
            [
                'name' => $user->name,
                'email' => $user->email,
                'actor' => $actor->name,
                'role' => $roleName,
            ],
            actionRoute: 'users.edit',
            actionParams: ['user' => $user->id],
        );

        return $user->load('roles');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, string $id, array $data): User
    {
        if (isset($data['role'])) {
            $this->assertRoleAssignable($actor, $data['role']);
        }

        $attributes = Arr::only($data, ['name', 'email']);

        if (isset($data['password']) && $data['password'] !== '') {
            $attributes['password'] = Hash::make($data['password']);
        }

        $user = $this->users->update($id, $attributes);

        if (isset($data['role'])) {
            $previousRole = $user->roles->first()?->name;
            $user->syncRoles([$data['role']]);

            if (app()->bound(NavigationTreeFlusher::class)) {
                app(NavigationTreeFlusher::class)
                    ->forgetPresentedTreeForUserId((string) $user->id);
            }

            $this->realtime->changed('role_assigned', null, (string) $user->id);

            if ($previousRole !== $data['role']) {
                activity()
                    ->useLog('user')
                    ->causedBy($actor)
                    ->performedOn($user)
                    ->event('role_assigned')
                    ->withProperties([
                        'previous_role' => $previousRole,
                        'role' => $data['role'],
                    ])
                    ->log('role_assigned');
            }
        }

        $this->realtime->userListChanged('updated', (string) $user->id);

        if ($actor->id !== $user->id) {
            // Personal notification: tell the affected user their account changed.
            // Force roles: [] so a polluted type-setting can't broadcast this
            // personal note to admin roles — audience is the subject only
            // (via notify_subject_user + subject_user_id).
            $this->notify(
                'account_updated',
                [
                    'name' => $user->name,
                    'actor' => $actor->name,
                    'subject_user_id' => $user->id,
                ],
                roles: [],
            );

            // Broadcast list-change signal to Root + Admin so any open /users
            // DataTable reloads live. Separate from the personal note above.
            $this->notify(
                'user_updated',
                [
                    'name' => $user->name,
                    'email' => $user->email,
                    'actor' => $actor->name,
                ],
                actionRoute: 'users.edit',
                actionParams: ['user' => $user->id],
            );
        }

        return $user->load('roles');
    }

    public function delete(User $actor, string $id): void
    {
        // Capture identity before soft delete so the broadcast can name the row.
        $user = $this->users->findByIdOrFail($id);
        $name = $user->name;
        $email = $user->email;

        $this->users->softDelete($id);

        $this->realtime->userListChanged('deleted', (string) $id);

        // Broadcast list-change signal to Root + Admin so open /users tables
        // reload. No action route — the row is gone.
        $this->notify(
            'user_deleted',
            [
                'name' => $name,
                'email' => $email,
                'actor' => $actor->name,
            ],
        );
    }

    private function assertRoleAssignable(User $actor, string $roleName): void
    {
        if (! $this->roleService->isRoleAssignableBy($actor, $roleName)) {
            abort(403, __('forms/users.role_not_assignable'));
        }
    }

    /**
     * Emit an activity notification through the kernel contract.
     *
     * The notification catalog (types, audience defaults, persistence, realtime)
     * belongs to `silverspoonmedia/notification`; Identity only decides *when*
     * to notify, so it hands over a type value. A host without that package
     * simply has no binding and user management keeps working without a bell.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>|null  $roles  null = the type's default audience.
     * @param  list<string>  $users
     * @param  array<string, mixed>  $actionParams
     */
    private function notify(
        string $type,
        array $data = [],
        ?array $roles = null,
        array $users = [],
        ?string $actionRoute = null,
        array $actionParams = [],
    ): void {
        if (! app()->bound(NotificationDispatcher::class)) {
            return;
        }

        app(NotificationDispatcher::class)->emit($type, $data, $users, $roles, $actionRoute, $actionParams);
    }
}
