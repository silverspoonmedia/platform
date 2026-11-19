<?php

namespace App\Domain\Identity\Services\Rbac;

use App\Domain\Identity\Enums\DomainKind;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Spatie\Permission\Guard;

class RoleService
{
    /**
     * Level terendah (angka terkecil) = kewenangan tertinggi.
     * Platform user management uses core roles only.
     */
    public function getLowestRoleLevel(User $user): ?int
    {
        return $this->getLowestRoleLevelFor($user, DomainKind::Core);
    }

    public function getLowestRoleLevelFor(User $user, DomainKind $kind): ?int
    {
        $levels = $this->getRoleLevelsFor($user, $kind);

        if ($levels->isEmpty()) {
            return null;
        }

        return $levels->min();
    }

    /**
     * @return Collection<int, int>
     */
    public function getRoleLevels(User $user): Collection
    {
        return $this->getRoleLevelsFor($user, DomainKind::Core);
    }

    /**
     * @return Collection<int, int>
     */
    public function getRoleLevelsFor(User $user, DomainKind $kind): Collection
    {
        return $user->roles()
            ->where('domain_kind', $kind)
            ->whereNotNull('level')
            ->pluck('level')
            ->map(fn ($level) => (int) $level);
    }

    /**
     * @return EloquentCollection<int, Role>
     */
    public function getAssignableRoles(User $actor): EloquentCollection
    {
        $actorLevel = $this->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return Role::query()->whereRaw('1 = 0')->get();
        }

        return Role::query()
            ->core()
            ->where('guard_name', Guard::getDefaultName($actor))
            ->where('level', '>', $actorLevel)
            ->orderBy('level')
            ->get();
    }

    public function isRoleAssignableBy(User $actor, string $roleName): bool
    {
        return $this->getAssignableRoles($actor)
            ->contains(fn (Role $role) => $role->name === $roleName);
    }

    /**
     * Untuk view/index/show: level efektif target (terendah) boleh sama atau lebih besar dari actor.
     * Peer selevel boleh saling lihat (read-only); hanya target dengan kewenangan lebih tinggi yang ditolak.
     * Hierarchy uses core roles only.
     */
    public function canActorViewTarget(User $actor, User $target): bool
    {
        $actorLevel = $this->getLowestRoleLevel($actor);
        $targetLevel = $this->getLowestRoleLevel($target);

        if ($actorLevel === null || $targetLevel === null) {
            return false;
        }

        return $targetLevel >= $actorLevel;
    }

    /**
     * Untuk edit/delete: seluruh role target harus berlevel lebih besar dari actor.
     * Hierarchy uses core roles only.
     */
    public function allTargetRolesAboveActor(User $actor, User $target): bool
    {
        $actorLevel = $this->getLowestRoleLevel($actor);

        if ($actorLevel === null) {
            return false;
        }

        $targetLevels = $this->getRoleLevels($target);

        if ($targetLevels->isEmpty()) {
            return false;
        }

        return $targetLevels->every(fn (int $level) => $level > $actorLevel);
    }
}
