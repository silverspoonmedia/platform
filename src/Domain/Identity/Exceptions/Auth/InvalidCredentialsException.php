<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class InvalidCredentialsException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.invalid_credentials'));
    }

    public function statusCode(): int
    {
        return 401;
    }
}
