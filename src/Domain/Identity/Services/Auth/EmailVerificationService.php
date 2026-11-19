<?php

namespace App\Domain\Identity\Services\Auth;

use App\Domain\Identity\Exceptions\Auth\EmailAlreadyVerifiedException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Auth\Events\Verified;

class EmailVerificationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function sendNotification(User $user): void
    {
        if ($user->hasVerifiedEmail()) {
            throw new EmailAlreadyVerifiedException;
        }

        $user->sendEmailVerificationNotification();
    }

    public function verify(string $userId, string $hash): User
    {
        $user = $this->users->findByIdOrFail($userId);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, __('api/auth.invalid_verification_link'));
        }

        if ($user->hasVerifiedEmail()) {
            throw new EmailAlreadyVerifiedException;
        }

        $this->users->markEmailAsVerified($userId);
        $user = $user->fresh() ?? $user;

        event(new Verified($user));

        return $user;
    }
}
