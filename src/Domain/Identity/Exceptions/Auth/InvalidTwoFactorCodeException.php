<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class InvalidTwoFactorCodeException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.invalid_two_factor_code'));
    }

    public function statusCode(): int
    {
        return 401;
    }
}
