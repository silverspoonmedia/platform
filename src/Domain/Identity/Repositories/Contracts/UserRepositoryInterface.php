<?php

namespace App\Domain\Identity\Repositories\Contracts;

use App\Domain\Identity\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByIdOrFail(string $id): User;

    public function findByEmail(string $email): ?User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): User;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $id, array $data): User;

    public function markEmailAsVerified(string $id): User;

    /**
     * @return Collection<int, User>
     */
    public function listVisibleToActorLevel(int $actorLevel): Collection;

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function paginateVisibleToActorLevel(int $actorLevel, int $perPage, int $page): LengthAwarePaginator;

    public function findByIdOrFailWithRoles(string $id): User;

    public function softDelete(string $id): void;
}
