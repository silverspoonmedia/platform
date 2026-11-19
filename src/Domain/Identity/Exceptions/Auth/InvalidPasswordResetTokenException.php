<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class InvalidPasswordResetTokenException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.invalid_reset_token'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
