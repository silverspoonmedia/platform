<?php

namespace App\Support\ActivityLog;

/**
 * Kernel-owned contract for writing one authentication-trail entry.
 *
 * Identity owns the events (login, logout, 2FA, password change) but not the
 * audit store: `silverspoonmedia/audit` owns the activity row and binds this
 * contract to its recorder. The kernel never imports the audit package, and a
 * host without it still authenticates — the call site checks `app()->bound()`.
 *
 * The payload is deliberately scalars, so the implementation resolves the
 * subject and causer from the id itself.
 */
interface AuthActivityRecorder
{
    /**
     * Record a successful sign-in.
     */
    public function recordLogin(string $userId, ?string $ipAddress, ?string $userAgent, string $loginMethod): void;

    /**
     * Record a rejected sign-in. The subject is resolved by the searched email
     * and may not exist, which is why no id is required here.
     */
    public function recordLoginFailed(string $email, ?string $ipAddress, ?string $userAgent): void;

    public function recordLogout(string $userId, ?string $ipAddress): void;

    public function recordTwoFactorChallenge(string $userId, ?string $ipAddress, ?string $userAgent): void;

    public function recordTwoFactorLoginFailed(string $ipAddress, ?string $userAgent, string $reason, ?string $userId = null): void;

    public function recordPasswordChanged(string $userId, ?string $ipAddress, string $method): void;

    public function recordTwoFactorEnabled(string $userId, ?string $ipAddress, ?string $userAgent): void;

    public function recordTwoFactorDisabled(string $userId, ?string $ipAddress, ?string $userAgent): void;
}
