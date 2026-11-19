<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessRuleException;

class CannotDeleteSelfException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct(__('api/users.cannot_delete_self'));
    }

    public function statusCode(): int
    {
        return 422;
    }
}
