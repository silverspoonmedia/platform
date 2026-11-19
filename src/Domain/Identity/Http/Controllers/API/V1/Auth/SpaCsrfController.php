<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\SpaCsrf;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Issues the double-submit CSRF nonce for the SPA's cross-site cookie auth.
 *
 * The SPA calls this once per session (and after a 419) before its first
 * mutation. The nonce is returned in the body AND set on a non-HttpOnly cookie;
 * the SPA echoes it back via the `X-CSRF-Token` header on subsequent mutations.
 */
class SpaCsrfController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $token = SpaCsrf::token();

        return $this->successResponse(
            ['token' => $token],
            __('api/common.spa_csrf_issued'),
        )->cookie(SpaCsrf::makeCookie($token));
    }
}
