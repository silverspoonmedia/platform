<?php

namespace App\Support\ListActions;

use App\Domain\Identity\Models\User;
use Illuminate\Support\Collection;

final class QueryRowActionAttacher
{
    /**
     * Zip resolved list rows with their source models and set `actions`.
     *
     * @param  array<int|string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public static function attach(
        array $rows,
        iterable $models,
        QueryRowActionBuilder $builder,
        ?User $actor = null,
    ): array {
        $items = self::listRows($rows);
        $modelList = Collection::make($models)->values();

        foreach ($items as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $model = $modelList->get($index);
            $items[$index]['actions'] = $model === null
                ? []
                : array_map(
                    static fn (QueryRowAction $action): array => $action->toArray(),
                    $builder->resolve($model, $actor),
                );
        }

        return array_values($items);
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return list<array<string, mixed>>
     */
    private static function listRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        if (array_is_list($rows)) {
            /** @var list<array<string, mixed>> $rows */
            return $rows;
        }

        $wrapped = $rows['data'] ?? null;
        if (is_array($wrapped) && array_is_list($wrapped)) {
            /** @var list<array<string, mixed>> $wrapped */
            return $wrapped;
        }

        /** @var list<array<string, mixed>> $values */
        $values = array_values($rows);

        return $values;
    }
}
