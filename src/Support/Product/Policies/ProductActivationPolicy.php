<?php

namespace App\Support\Product\Policies;

use App\Domain\Identity\Models\User;
use App\Support\Product\Models\ProductActivation;

class ProductActivationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('view product activations');
    }

    public function view(User $user, ProductActivation $activation): bool
    {
        if ($user->can('view product activations')) {
            return true;
        }

        return $user->id === $activation->user_id;
    }

    public function update(User $user, ProductActivation $activation): bool
    {
        return $user->can('manage product activations');
    }

    public function manage(User $user): bool
    {
        return $user->can('manage product activations');
    }
}
