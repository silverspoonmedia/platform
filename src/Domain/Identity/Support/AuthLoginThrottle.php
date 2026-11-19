<?php

namespace App\Domain\Identity\Support;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Identity-scoped login throttle.
 *
 * Replaces the route-level `throttle:5,1` middleware for credential endpoints:
 * that middleware keys on `domain|ip` for every guest, so one user failing on a
 * shared IP (NAT/CGNAT/office) locked out every other user on the same IP, and
 * validation/CSRF rejects still burned quota. Here the counter only increments
 * on actual credential failures (recordFailure), keyed by email+IP, with a
 * coarser per-IP backstop to keep cross-email brute force contained.
 */
class AuthLoginThrottle
{
    /**
     * Resolves the client IP lazily per call: services depending on this class
     * may be constructed once and reused across requests (Octane), so the
     * request must never be captured in the constructor.
     */
    private function ip(): string
    {
        return (string) (request()->ip() ?? 'unknown');
    }

    /**
     * Throws 429 when the identity or its IP has exhausted attempts.
     * Called before credentials are attempted; does not consume quota.
     *
     * @throws ThrottleRequestsException
     */
    public function ensureNotLocked(string $scope): void
    {
        if (RateLimiter::tooManyAttempts($this->identityKey($scope), $this->maxAttempts())) {
            $this->throwLocked($this->identityKey($scope), $this->maxAttempts());
        }

        if (RateLimiter::tooManyAttempts($this->ipKey(), $this->ipBackstopMax())) {
            $this->throwLocked($this->ipKey(), $this->ipBackstopMax());
        }
    }

    /**
     * Consume one attempt after a credential failure. Successful logins and
     * requests rejected before the credential check never reach this.
     */
    public function recordFailure(string $scope): void
    {
        RateLimiter::hit($this->identityKey($scope), $this->decaySeconds());
        RateLimiter::hit($this->ipKey(), $this->decaySeconds());
    }

    /**
     * Reset the identity counter once valid credentials are presented, so a
     * user who recovers the right password is not kept out by earlier typos.
     * The IP backstop intentionally survives — it tracks the address, not
     * the credentials.
     */
    public function clear(string $scope): void
    {
        RateLimiter::clear($this->identityKey($scope));
    }

    private function identityKey(string $scope): string
    {
        $scope = Str::lower(trim($scope));
        $identity = ($scope !== '' ? $scope : 'guest').'|'.$this->ip();

        return 'auth-login:'.sha1($identity);
    }

    private function ipKey(): string
    {
        return 'auth-login-ip:'.$this->ip();
    }

    private function maxAttempts(): int
    {
        return (int) config('auth.login_throttle.max_attempts', 5);
    }

    private function decaySeconds(): int
    {
        return (int) config('auth.login_throttle.decay_seconds', 60);
    }

    private function ipBackstopMax(): int
    {
        return (int) config('auth.login_throttle.ip_backstop_max', 20);
    }

    /**
     * @throws ThrottleRequestsException
     */
    private function throwLocked(string $key, int $maxAttempts): never
    {
        $retryAfter = RateLimiter::availableIn($key);

        throw new ThrottleRequestsException(
            __('api/auth.login_throttled', ['seconds' => $retryAfter]),
            null,
            [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => time() + $retryAfter,
            ],
        );
    }
}
