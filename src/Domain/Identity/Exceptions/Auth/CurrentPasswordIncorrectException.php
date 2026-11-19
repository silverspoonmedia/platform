<?php

namespace App\Domain\Identity\Exceptions\Auth;

use App\Exceptions\BusinessRuleException;

class CurrentPasswordIncorrectException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/auth.current_password_incorrect'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
