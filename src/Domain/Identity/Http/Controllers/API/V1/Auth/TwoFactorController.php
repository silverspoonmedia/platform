<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Domain\Identity\Http\Requests\Auth\TwoFactorLoginRequest;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Domain\Identity\Services\Auth\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Support\Auth\SpaAuthCookie;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TwoFactorController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly TwoFactorService $service,
        private readonly AuthService $authService,
    ) {}

    public function startSetup(): JsonResponse
    {
        $payload = $this->service->startSetup(
            Auth::guard('api')->user(),
        );

        return $this->successResponse($payload, __('api/auth.two_factor_setup_started'));
    }

    public function confirmSetup(TwoFactorCodeRequest $request): JsonResponse
    {
        $payload = $this->service->confirmSetup(
            Auth::guard('api')->user(),
            (string) $request->validated('code'),
        );

        return $this->successResponse($payload, __('api/auth.two_factor_enabled'));
    }

    public function disable(TwoFactorCodeRequest $request): JsonResponse
    {
        $this->service->disable(
            Auth::guard('api')->user(),
            (string) $request->validated('code'),
        );

        return $this->successResponse(null, __('api/auth.two_factor_disabled'));
    }

    /**
     * Second-step login: the client received a `two_factor_token` via the standard
     * login response (status of `two_factor_required`) and now submits it together
     * with the TOTP code. On success this issues a real JWT.
     */
    public function login(TwoFactorLoginRequest $request): JsonResponse
    {
        $payload = $this->authService->loginWithTwoFactorToken(
            (string) $request->validated('two_factor_token'),
            (string) $request->validated('code'),
        );

        $response = $this->successResponse($payload, __('api/auth.login_success'));

        $token = $payload['token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;
        if (is_string($token) && $token !== '' && is_int($expiresIn) && $expiresIn > 0) {
            $response->cookie(SpaAuthCookie::make($token, $expiresIn));

            // Cookie-mode keeps the JWT HttpOnly-only (see AuthController::withSpaCookie).
            $body = $response->getData(true);
            if (isset($body['data']['token'])) {
                unset($body['data']['token']);
                $response->setData($body);
            }
        }

        return $response;
    }
}
