<?php

namespace App\Domain\Identity\Exceptions;

use App\Exceptions\BusinessRuleException;

class IdentityOAuthException extends BusinessRuleException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 422,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function statusCode(): int
    {
        return $this->httpStatus;
    }
}
