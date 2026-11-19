<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\EloquentCrudRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class EloquentCrudRepository implements EloquentCrudRepositoryInterface
{
    public function all(string|Model $model, array $columns = ['*']): Collection
    {
        return $this->newQuery($model)->get($columns);
    }

    public function find(string|Model $model, mixed $id, array $columns = ['*']): ?Model
    {
        return $this->newQuery($model)->find($id, $columns);
    }

    public function findOrFail(string|Model $model, mixed $id, array $columns = ['*']): Model
    {
        return $this->newQuery($model)->findOrFail($id, $columns);
    }

    public function findMany(string|Model $model, array $ids, array $columns = ['*']): Collection
    {
        return $this->newQuery($model)->findMany($ids, $columns);
    }

    public function paginate(string|Model $model, ?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        return $this->newQuery($model)->paginate($perPage, $columns, $pageName, $page);
    }

    public function create(string|Model $model, array $attributes): Model
    {
        return $this->newQuery($model)->create($attributes);
    }

    public function update(string|Model $model, mixed $id, array $attributes): Model
    {
        $record = $this->findOrFail($model, $id);
        $record->update($attributes);

        return $record->fresh();
    }

    public function delete(string|Model $model, mixed $id): bool
    {
        $record = $this->findOrFail($model, $id);

        return (bool) $record->delete();
    }

    public function restore(string|Model $model, mixed $id): bool
    {
        $this->assertSoftDeletes($model);
        $record = $this->newQuery($model)->withTrashed()->findOrFail($id);

        return (bool) $record->restore();
    }

    public function forceDelete(string|Model $model, mixed $id): bool
    {
        $this->assertSoftDeletes($model);
        $record = $this->newQuery($model)->withTrashed()->findOrFail($id);

        return (bool) $record->forceDelete();
    }

    public function onlyTrashed(string|Model $model, array $columns = ['*']): Collection
    {
        $this->assertSoftDeletes($model);

        return $this->newQuery($model)->onlyTrashed()->get($columns);
    }

    public function query(string|Model $model): Builder
    {
        return $this->newQuery($model);
    }

    public function first(string|Model $model, ?Closure $constraint = null): ?Model
    {
        $query = $this->newQuery($model);

        if ($constraint !== null) {
            $constraint($query);
        }

        return $query->first();
    }

    public function exists(string|Model $model, mixed $id): bool
    {
        return $this->newQuery($model)->whereKey($id)->exists();
    }

    public function count(string|Model $model, ?Closure $constraint = null): int
    {
        $query = $this->newQuery($model);

        if ($constraint !== null) {
            $constraint($query);
        }

        return $query->count();
    }

    public function updateOrCreate(string|Model $model, array $attributes, array $values = []): Model
    {
        return $this->newQuery($model)->updateOrCreate($attributes, $values);
    }

    public function firstOrCreate(string|Model $model, array $attributes, array $values = []): Model
    {
        return $this->newQuery($model)->firstOrCreate($attributes, $values);
    }

    private function newQuery(string|Model $model): Builder
    {
        $instance = $model instanceof Model ? $model : new $model;

        return $instance->newQuery();
    }

    private function assertSoftDeletes(string|Model $model): void
    {
        if (! $this->usesSoftDeletes($model)) {
            throw new InvalidArgumentException(
                sprintf('Model %s does not use the SoftDeletes trait.', $this->resolveClass($model)),
            );
        }
    }

    private function usesSoftDeletes(string|Model $model): bool
    {
        return in_array(SoftDeletes::class, class_uses_recursive($this->resolveClass($model)), true);
    }

    private function resolveClass(string|Model $model): string
    {
        return $model instanceof Model ? $model::class : $model;
    }
}
