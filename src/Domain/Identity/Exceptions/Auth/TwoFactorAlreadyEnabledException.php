<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class TwoFactorAlreadyEnabledException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.two_factor_already_enabled'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
