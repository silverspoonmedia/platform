<?php

namespace App\Support\Product\Exceptions;

use App\Exceptions\BusinessRuleException;
use Symfony\Component\HttpFoundation\Response;

class ProductNotFoundException extends BusinessRuleException
{
    public function statusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
