<?php

namespace App\Traits;

use App\Support\ApiResponder;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function successResponse(mixed $data, ?string $message = null, int $code = 200): JsonResponse
    {
        return ApiResponder::success($data, $message, $code);
    }

    protected function errorResponse(string $message, int $code, mixed $data = null): JsonResponse
    {
        return ApiResponder::error($message, $code, $data);
    }
}
