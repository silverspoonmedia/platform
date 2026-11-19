<?php

namespace App\Support\Auth;

use App\Domain\Identity\Models\User;
use App\Support\Presentation\DatePresenter;

class UserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(User $user): array
    {
        [$iso, $formatted, $human] = DatePresenter::pack($user->email_verified_at);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $iso,
            'email_verified_at_formatted' => $formatted,
            'email_verified_at_human' => $human,
            'two_factor_enabled' => $user->hasTwoFactorSecret() && $user->two_factor_confirmed_at !== null,
            'roles' => $user->getRoleNames()->values()->all(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
        ];
    }
}
