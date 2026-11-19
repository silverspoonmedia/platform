<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class EmailAlreadyVerifiedException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.email_already_verified'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
