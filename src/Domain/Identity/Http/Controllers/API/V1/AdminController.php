<?php

namespace App\Domain\Identity\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    use ApiResponse;

    public function ping(): JsonResponse
    {
        return $this->successResponse(null, __('api/admin.access_granted'));
    }
}
