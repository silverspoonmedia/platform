<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Requests\Auth\LoginRequest;
use App\Domain\Identity\Http\Requests\Auth\RegisterRequest;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Http\Controllers\Controller;
use App\Support\Auth\SpaAuthCookie;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $payload = $this->authService->register($request->validated());

        return $this->withSpaCookie(
            $this->successResponse($payload, __('api/auth.register_success'), 201),
            $payload,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $payload = $this->authService->login($request->validated());

        return $this->withSpaCookie(
            $this->successResponse($payload, __('api/auth.login_success')),
            $payload,
        );
    }

    public function me(): JsonResponse
    {
        return $this->successResponse(
            $this->authService->me(),
            __('api/auth.me_success'),
        );
    }

    public function refresh(): JsonResponse
    {
        $payload = $this->authService->refresh();

        return $this->withSpaCookie(
            $this->successResponse($payload, __('api/auth.refresh_success')),
            $payload,
        );
    }

    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->successResponse(null, __('api/auth.logout_success'))
            ->cookie(SpaAuthCookie::forget());
    }

    /**
     * Attach the cross-site HttpOnly JWT cookie when the payload carries a token.
     * Two-factor challenges return no token, so they are left cookieless.
     *
     * @param  array<string, mixed>  $payload
     */
    private function withSpaCookie(JsonResponse $response, array $payload): JsonResponse
    {
        $token = $payload['token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (is_string($token) && $token !== '' && is_int($expiresIn) && $expiresIn > 0) {
            $response->cookie(SpaAuthCookie::make($token, $expiresIn));

            // Cookie-mode keeps the JWT HttpOnly-only: strip it from the JSON body
            // so an XSS payload in the SPA cannot read and exfiltrate it. The SPA
            // never reads data.token (it uses user/expires_in/remember only).
            $body = $response->getData(true);
            if (isset($body['data']['token'])) {
                unset($body['data']['token']);
                $response->setData($body);
            }
        }

        return $response;
    }
}
