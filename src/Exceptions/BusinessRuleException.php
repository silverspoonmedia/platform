<?php

namespace App\Exceptions;

use App\Support\ApiResponder;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class BusinessRuleException extends Exception
{
    abstract public function statusCode(): int;

    public function render(Request $request): JsonResponse
    {
        return ApiResponder::error($this->getMessage(), $this->statusCode());
    }
}
