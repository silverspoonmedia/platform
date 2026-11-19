<?php

namespace App\Domain\Identity\Services\Auth;

use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginSuccessful;
use App\Domain\Identity\Events\TwoFactorChallengeIssued;
use App\Domain\Identity\Events\TwoFactorLoginFailed;
use App\Domain\Identity\Events\UserLoggedOut;
use App\Domain\Identity\Exceptions\Auth\InvalidCredentialsException;
use App\Domain\Identity\Exceptions\Auth\InvalidTwoFactorCodeException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Contracts\UserRepositoryInterface;
use App\Domain\Identity\Support\AuthLoginThrottle;
use App\Support\Auth\SpaAuthCookie;
use App\Support\Auth\UserPresenter;
use App\Support\Encryption\Cache\SensitiveCacheValue;
use App\Support\Product\Exceptions\ProductActivationException;
use App\Support\Product\Exceptions\ProductNotFoundException;
use App\Support\Product\ProductRegistry;
use App\Support\Product\Services\ProductActivationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\JWTGuard;

class AuthService
{
    private const TWO_FACTOR_CACHE_PREFIX = '2fa_token:';

    private const TWO_FACTOR_CACHE_TTL = 300;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly TwoFactorService $twoFactor,
        private readonly SensitiveCacheValue $sensitiveCache,
        private readonly ProductActivationService $productActivations,
        private readonly AuthLoginThrottle $loginThrottle,
    ) {}

    /**
     * @param  array{name: string, email: string, password: string}  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = $this->users->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole((string) setting_config('auth.default_role', 'User'));
            $user->sendEmailVerificationNotification();

            $this->activateImplicitProducts($user);

            return $this->buildAuthPayload($user);
        });
    }

    /**
     * Auto-activate every implicit product for a newly registered user.
     * Failures are logged but do not abort registration — a user without
     * an activation row still authenticates; the middleware lazy-creates
     * implicit activations on first product access.
     */
    private function activateImplicitProducts(User $user): void
    {
        foreach (ProductRegistry::implicitProductKeys() as $productKey) {
            try {
                $this->productActivations->activateImplicit($user, $productKey, 'register_hook');
            } catch (ProductActivationException|ProductNotFoundException $e) {
                Log::warning('auth.register.product_activation_failed', [
                    'user_id' => $user->id,
                    'product_key' => $productKey,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array{email: string, password: string, remember_me?: bool}  $credentials
     * @return array<string, mixed>
     */
    public function login(array $credentials): array
    {
        $remember = (bool) ($credentials['remember_me'] ?? false);
        $credentials = Arr::only($credentials, ['email', 'password']);

        $this->loginThrottle->ensureNotLocked((string) $credentials['email']);

        try {
            return $this->withTokenLifetime($remember, function (int $ttlMinutes) use ($credentials, $remember) {
                Auth::forgetGuards();
                $guard = $this->guard();
                $guard->setTTL($ttlMinutes);
                $guard->claims($this->customClaims($remember));

                if (! $token = $guard->attempt($credentials)) {
                    throw new InvalidCredentialsException;
                }

                // Valid credentials: reset any quota burned by earlier wrong
                // passwords so a recovered user is never locked out.
                $this->loginThrottle->clear((string) $credentials['email']);

                /** @var User $user */
                $user = $guard->user();

                if ($this->twoFactor->enabled($user)) {
                    $guard->setToken($token)->invalidate();

                    TwoFactorChallengeIssued::dispatch(
                        $user,
                        request()->ip() ?? 'unknown',
                        request()->userAgent(),
                    );

                    return $this->buildTwoFactorChallengePayload($user, $remember);
                }

                LoginSuccessful::dispatch(
                    $user,
                    request()->ip() ?? 'unknown',
                    request()->userAgent()
                );

                return $this->buildAuthPayload($user, $token, $ttlMinutes, $remember);
            });
        } catch (InvalidCredentialsException $e) {
            $this->loginThrottle->recordFailure((string) ($credentials['email'] ?? ''));

            LoginFailed::dispatch(
                $credentials['email'] ?? 'unknown',
                request()->ip() ?? 'unknown',
                request()->userAgent()
            );

            throw $e;
        }
    }

    /**
     * Refresh the JWT currently on the request (Authorization: Bearer).
     * Works for valid tokens and expired tokens still inside jwt.refresh_ttl.
     *
     * @return array{user: array<string, mixed>, token: string, token_type: string, expires_in: int, remember: bool}
     */
    public function refresh(): array
    {
        // Cookie-mode SPA sends no Bearer: fall back to the HttpOnly JWT cookie.
        $tokenString = request()->bearerToken();

        if (! is_string($tokenString) || $tokenString === '') {
            $tokenString = request()->cookie(SpaAuthCookie::NAME);
        }

        if (! is_string($tokenString) || $tokenString === '') {
            throw new JWTException('Token not provided');
        }

        return $this->refreshFromToken($tokenString);
    }

    /**
     * Refresh a raw JWT string (cookie or Bearer). Single refresh - no double-issue.
     * Expired tokens are accepted while within jwt.refresh_ttl (package refresh flow).
     *
     * @return array{user: array<string, mixed>, token: string, token_type: string, expires_in: int, remember: bool}
     */
    public function refreshFromToken(string $token): array
    {
        $remember = $this->resolveRememberForRefresh($token);

        return $this->withTokenLifetime($remember, function (int $ttlMinutes) use ($token, $remember) {
            Auth::forgetGuards();
            $guard = $this->guard();
            $guard->setTTL($ttlMinutes);
            $guard->setToken($token);

            $newToken = $guard->refresh();

            /** @var User|null $user */
            $user = $guard->setToken($newToken)->user();

            if (! $user instanceof User) {
                throw new JWTException('User not found');
            }

            return $this->buildAuthPayload($user, $newToken, $ttlMinutes, $remember);
        });
    }

    /**
     * Preserve the remember flag across token refresh.
     *
     * Priority:
     * 1. Explicit `remember` in the request body (SPA opt-in for this refresh).
     * 2. `rm` custom claim carried on the original token (set at login).
     * 3. Fallback false.
     */
    private function resolveRememberForRefresh(string $token): bool
    {
        $request = request();
        if ($request?->has('remember')) {
            return $request->boolean('remember');
        }

        try {
            $payload = $this->guard()->setToken($token)->payload();
            /** @var mixed $rm */
            $rm = $payload->get('rm');

            return $rm === true || $rm === 1 || $rm === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Custom JWT claims injected at login so they persist across refreshes
     * (configured via `jwt.persistent_claims`).
     *
     * @return array<string, mixed>
     */
    private function customClaims(bool $remember): array
    {
        return ['rm' => $remember ? 1 : 0];
    }

    public function loginWithTwoFactorToken(string $twoFactorToken, string $code): array
    {
        $scope = '2fa:'.sha1($twoFactorToken);

        $this->loginThrottle->ensureNotLocked($scope);

        $cacheKey = $this->twoFactorCacheKey($twoFactorToken);
        $stored = Cache::pull($cacheKey);
        $challenge = is_string($stored)
            ? $this->sensitiveCache->unwrap($cacheKey, $stored)
            : $stored;

        if (! is_array($challenge) || ! isset($challenge['user_id'], $challenge['remember'])) {
            $this->loginThrottle->recordFailure($scope);

            TwoFactorLoginFailed::dispatch(
                request()->ip() ?? 'unknown',
                'invalid_token',
                request()->userAgent(),
            );

            throw new InvalidTwoFactorCodeException;
        }

        $user = User::query()->find((string) $challenge['user_id']);

        if (! $user || ! $this->twoFactor->verifyLogin($user, $code)) {
            $this->loginThrottle->recordFailure($scope);

            TwoFactorLoginFailed::dispatch(
                request()->ip() ?? 'unknown',
                'invalid_code',
                request()->userAgent(),
                $user,
            );

            throw new InvalidTwoFactorCodeException;
        }

        LoginSuccessful::dispatch(
            $user,
            request()->ip() ?? 'unknown',
            request()->userAgent(),
            'two_factor',
        );

        return $this->withTokenLifetime((bool) $challenge['remember'], function (int $ttlMinutes) use ($user, $challenge) {
            Auth::forgetGuards();
            $guard = $this->guard();
            $guard->setTTL($ttlMinutes);

            $token = $guard->login($user, $this->customClaims((bool) $challenge['remember']));

            return $this->buildAuthPayload($user, $token, $ttlMinutes, (bool) $challenge['remember']);
        });
    }

    public function logout(): void
    {
        /** @var User|null $user */
        $user = Auth::guard('api')->user();

        Auth::guard('api')->logout();
        Auth::forgetGuards();

        if ($user) {
            UserLoggedOut::dispatch(
                $user,
                request()->ip() ?? 'unknown'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function me(): array
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return UserPresenter::present($user);
    }

    /**
     * @param  callable(int): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function withTokenLifetime(bool $remember, callable $callback): array
    {
        $defaultTtl = $this->defaultTtlMinutes();
        $ttlMinutes = $remember ? $this->rememberTtlMinutes() : $defaultTtl;

        try {
            return $callback($ttlMinutes);
        } finally {
            Auth::forgetGuards();
            app()->forgetInstance('tymon.jwt');
            app()->forgetInstance('tymon.jwt.provider.jwt');
            app()->forgetInstance('tymon.jwt.manager');
        }
    }

    private function guard(): JWTGuard
    {
        /** @var JWTGuard $guard */
        $guard = Auth::guard('api');

        return $guard;
    }

    private function defaultTtlMinutes(): int
    {
        return (int) setting_config('jwt.ttl', 60);
    }

    private function rememberTtlMinutes(): int
    {
        return (int) setting_config('jwt.remember_ttl', setting_config('jwt.refresh_ttl', 20160));
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function buildTwoFactorChallengePayload(User $user, bool $remember): array
    {
        $twoFactorToken = (string) Str::uuid();

        $cacheKey = $this->twoFactorCacheKey($twoFactorToken);

        Cache::put(
            $cacheKey,
            $this->sensitiveCache->wrap($cacheKey, ['user_id' => $user->id, 'remember' => $remember]),
            self::TWO_FACTOR_CACHE_TTL,
        );

        return [
            'user' => UserPresenter::present($user),
            'two_factor_required' => true,
            'two_factor_token' => $twoFactorToken,
            'two_factor_token_expires_in' => self::TWO_FACTOR_CACHE_TTL,
            'remember' => $remember,
        ];
    }

    private function twoFactorCacheKey(string $token): string
    {
        return self::TWO_FACTOR_CACHE_PREFIX.$token;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAuthPayload(
        User $user,
        ?string $token = null,
        ?int $ttlMinutes = null,
        bool $remember = false,
    ): array {
        if ($token === null) {
            return $this->withTokenLifetime($remember, function (int $ttl) use ($user, $ttlMinutes, $remember) {
                Auth::forgetGuards();
                $guard = $this->guard();
                $guard->setTTL($ttlMinutes ?? $ttl);
                $token = $guard->login($user, $this->customClaims($remember));
                $actualTtl = $ttlMinutes ?? $guard->getTTL() ?? $this->defaultTtlMinutes();

                return [
                    'user' => UserPresenter::present($user),
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => $actualTtl * 60,
                    'remember' => $remember,
                ];
            });
        }

        $ttlMinutes ??= $this->defaultTtlMinutes();

        return [
            'user' => UserPresenter::present($user),
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
            'remember' => $remember,
        ];
    }
}
