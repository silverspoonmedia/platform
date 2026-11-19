<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\EmailVerificationService;
use App\Http\Controllers\Controller;
use App\Support\Auth\UserPresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmailVerificationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly EmailVerificationService $emailVerificationService,
    ) {}

    public function verify(string $id, string $hash): JsonResponse
    {
        $user = $this->emailVerificationService->verify($id, $hash);

        return $this->successResponse(
            UserPresenter::present($user),
            __('api/auth.email_verified'),
        );
    }

    public function send(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $this->emailVerificationService->sendNotification($user);

        return $this->successResponse(
            null,
            __('api/auth.verification_sent'),
        );
    }
}
