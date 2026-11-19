<?php

namespace App\Support\ListActions;

use App\Domain\Identity\Models\User;

interface QueryRowActionBuilder
{
    /**
     * @return list<QueryRowAction>
     */
    public function resolve(mixed $model, ?User $actor = null): array;
}
