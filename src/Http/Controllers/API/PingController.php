<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        return $this->successResponse(null, __('api/common.pong'), 200);
    }
}
