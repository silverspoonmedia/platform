<?php

namespace App\Domain\Identity\Services\Auth;

use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Exceptions\Auth\CurrentPasswordIncorrectException;
use App\Domain\Identity\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Support\AuthStateBroadcast;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AuthStateBroadcast $broadcast = new AuthStateBroadcast,
    ) {}

    public function sendResetLink(string $email): void
    {
        Password::broker()->sendResetLink(['email' => $email]);
    }

    /**
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $data
     */
    public function reset(array $data): void
    {
        $status = Password::broker()->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $data['password_confirmation'] ?? $data['password'],
                'token' => $data['token'],
            ],
            function ($user, string $password): void {
                $this->users->update($user->id, [
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ]);

                event(new PasswordReset($user));

                PasswordChanged::dispatch(
                    $user,
                    request()->ip() ?? 'unknown',
                    'reset',
                );

                $this->broadcast->security($user, 'password_changed', 'reset');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw new InvalidPasswordResetTokenException;
        }
    }

    /**
     * @param  array{current_password: string, password: string}  $data
     */
    public function changePassword(string $userId, array $data): void
    {
        $user = $this->users->findByIdOrFail($userId);

        if (! Hash::check($data['current_password'], $user->password)) {
            throw new CurrentPasswordIncorrectException;
        }

        $this->users->update($userId, [
            'password' => $data['password'],
        ]);

        PasswordChanged::dispatch(
            $user->fresh() ?? $user,
            request()->ip() ?? 'unknown',
            'change',
        );

        $this->broadcast->security($user->fresh() ?? $user, 'password_changed', 'change');
    }
}
