<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Requests\Auth\ChangePasswordRequest;
use App\Domain\Identity\Http\Requests\Auth\ForgotPasswordRequest;
use App\Domain\Identity\Http\Requests\Auth\ResetPasswordRequest;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\PasswordResetService;
use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PasswordController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PasswordResetService $passwordResetService,
    ) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->sendResetLink($request->validated('email'));

        return $this->successResponse(
            null,
            __('api/auth.password_forgot'),
        );
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $this->passwordResetService->reset($request->validated());

        return $this->successResponse(null, __('api/auth.password_reset'));
    }

    public function update(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $this->passwordResetService->changePassword($user->id, $request->validated());

        return $this->successResponse(null, __('api/auth.password_updated'));
    }
}
