<?php

namespace App\Domain\Identity\Services\Lookup;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Rbac\RoleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Select2 lookups for the SPA (`/api/v1/lookups/*`).
 *
 * Users and roles only. The menu lookup moved to `silverspoonmedia/navigation`
 * (`MenuLookupService`, served by `/api/v1/navigation/lookups/menus`) because
 * the kernel must not import the navigation package's model.
 */
class LookupService
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * @param  list<string>  $ids
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function users(User $actor, ?string $search, int $page, int $perPage, array $ids = []): array
    {
        if ($ids !== []) {
            return $this->hydrateUsers($actor, $ids);
        }

        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return $this->emptyPage();
        }

        $query = $this->usersVisibleToActorQuery($actorLevel);

        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $query->orderBy('name');

        return $this->paginateUsers($query, $page, $perPage);
    }

    /**
     * @param  list<string>  $ids
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    public function roles(?string $search, int $page, int $perPage, array $ids = []): array
    {
        if ($ids !== []) {
            return $this->hydrateRoles($ids);
        }

        $query = Role::query()
            ->core()
            ->where('guard_name', 'api')
            ->orderBy('level');

        if ($search !== null) {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where('name', 'like', $like);
        }

        $offset = ($page - 1) * $perPage;
        $rows = (clone $query)->skip($offset)->take($perPage + 1)->get();
        $more = $rows->count() > $perPage;

        if ($more) {
            $rows = $rows->take($perPage);
        }

        return [
            'results' => $rows->map(fn (Role $role) => [
                'id' => (string) $role->id,
                'text' => (string) $role->name,
            ])->values()->all(),
            'pagination' => ['more' => $more],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function hydrateUsers(User $actor, array $ids): array
    {
        $actorLevel = $this->roleService->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return $this->emptyPage(false);
        }

        /** @var Collection<int, User> $users */
        $users = $this->usersVisibleToActorQuery($actorLevel)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();

        return [
            'results' => $users->map(fn (User $user) => $this->userResult($user))->values()->all(),
            'pagination' => ['more' => false],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function hydrateRoles(array $ids): array
    {
        $roles = Role::query()
            ->core()
            ->where('guard_name', 'api')
            ->whereIn('id', $ids)
            ->orderBy('level')
            ->get();

        return [
            'results' => $roles->map(fn (Role $role) => [
                'id' => (string) $role->id,
                'text' => (string) $role->name,
            ])->values()->all(),
            'pagination' => ['more' => false],
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function paginateUsers(Builder $query, int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;

        /** @var Collection<int, User> $rows */
        $rows = (clone $query)->skip($offset)->take($perPage + 1)->get();
        $more = $rows->count() > $perPage;

        if ($more) {
            $rows = $rows->take($perPage);
        }

        return [
            'results' => $rows->map(fn (User $user) => $this->userResult($user))->values()->all(),
            'pagination' => ['more' => $more],
        ];
    }

    private function usersVisibleToActorQuery(int $actorLevel): Builder
    {
        $userModel = new User;
        $morphKey = config('permission.column_names.model_morph_key', 'model_id');
        $rolesTable = config('permission.table_names.roles', 'roles');
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        /** @var Builder<User> $query */
        $query = User::query()
            ->whereIn('id', function ($query) use ($actorLevel, $userModel, $morphKey, $rolesTable, $pivotTable): void {
                $query->select($pivotTable.'.'.$morphKey)
                    ->from($pivotTable)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$pivotTable}.role_id")
                    ->where("{$pivotTable}.model_type", $userModel->getMorphClass())
                    ->where("{$rolesTable}.domain_kind", 'core')
                    ->whereNotNull("{$rolesTable}.level")
                    ->groupBy("{$pivotTable}.{$morphKey}")
                    ->havingRaw('MIN('.$rolesTable.'.level) >= ?', [$actorLevel]);
            });

        return $query;
    }

    /**
     * @return array{id: string, text: string}
     */
    private function userResult(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'text' => $user->name.' ('.$user->email.')',
        ];
    }

    /**
     * @return array{results: list<array{id: string, text: string}>, pagination: array{more: bool}}
     */
    private function emptyPage(bool $more = false): array
    {
        return [
            'results' => [],
            'pagination' => ['more' => $more],
        ];
    }
}
