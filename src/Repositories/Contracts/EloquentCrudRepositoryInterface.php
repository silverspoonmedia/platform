<?php

namespace App\Repositories\Contracts;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface EloquentCrudRepositoryInterface
{
    public function all(string|Model $model, array $columns = ['*']): Collection;

    public function find(string|Model $model, mixed $id, array $columns = ['*']): ?Model;

    public function findOrFail(string|Model $model, mixed $id, array $columns = ['*']): Model;

    public function findMany(string|Model $model, array $ids, array $columns = ['*']): Collection;

    public function paginate(string|Model $model, ?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator;

    public function create(string|Model $model, array $attributes): Model;

    public function update(string|Model $model, mixed $id, array $attributes): Model;

    public function delete(string|Model $model, mixed $id): bool;

    public function restore(string|Model $model, mixed $id): bool;

    public function forceDelete(string|Model $model, mixed $id): bool;

    public function onlyTrashed(string|Model $model, array $columns = ['*']): Collection;

    public function query(string|Model $model): Builder;

    public function first(string|Model $model, ?Closure $constraint = null): ?Model;

    public function exists(string|Model $model, mixed $id): bool;

    public function count(string|Model $model, ?Closure $constraint = null): int;

    public function updateOrCreate(string|Model $model, array $attributes, array $values = []): Model;

    public function firstOrCreate(string|Model $model, array $attributes, array $values = []): Model;
}
