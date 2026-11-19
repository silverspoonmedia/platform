<?php

namespace App\Domain\Identity\Listeners;

use App\Domain\Identity\Events\LoginFailed;
use App\Domain\Identity\Events\LoginSuccessful;
use App\Domain\Identity\Events\PasswordChanged;
use App\Domain\Identity\Events\TwoFactorChallengeIssued;
use App\Domain\Identity\Events\TwoFactorDisabled;
use App\Domain\Identity\Events\TwoFactorEnabled;
use App\Domain\Identity\Events\TwoFactorLoginFailed;
use App\Domain\Identity\Events\UserLoggedOut;
use Illuminate\Support\Facades\Log;

/**
 * Writes auth domain events to Laravel's file log.
 *
 * Synchronous for the same reason as {@see RecordAuthenticationActivity}:
 * audit visibility must not depend on a queue worker in local/dev.
 */
class LogAuthenticationEvents
{
    public function handleLoginSuccessful(LoginSuccessful $event): void
    {
        Log::info('User logged in successfully', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleLoginFailed(LoginFailed $event): void
    {
        Log::warning('Login attempt failed', [
            'email' => $event->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleUserLoggedOut(UserLoggedOut $event): void
    {
        Log::info('User logged out', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
        ]);
    }

    public function handleTwoFactorChallengeIssued(TwoFactorChallengeIssued $event): void
    {
        Log::info('Two-factor challenge issued', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleTwoFactorLoginFailed(TwoFactorLoginFailed $event): void
    {
        Log::warning('Two-factor login failed', [
            'user_id' => $event->user?->id,
            'email' => $event->user?->email,
            'reason' => $event->reason,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handlePasswordChanged(PasswordChanged $event): void
    {
        Log::info('User password changed', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'method' => $event->method,
            'ip_address' => $event->ipAddress,
        ]);
    }

    public function handleTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        Log::info('Two-factor authentication enabled', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    public function handleTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        Log::info('Two-factor authentication disabled', [
            'user_id' => $event->user->id,
            'email' => $event->user->email,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
        ]);
    }

    /**
     * Register event listener subscriptions.
     *
     * @return array<string, string>
     */
    public function subscribe(): array
    {
        return [
            LoginSuccessful::class => 'handleLoginSuccessful',
            LoginFailed::class => 'handleLoginFailed',
            UserLoggedOut::class => 'handleUserLoggedOut',
            TwoFactorChallengeIssued::class => 'handleTwoFactorChallengeIssued',
            TwoFactorLoginFailed::class => 'handleTwoFactorLoginFailed',
            PasswordChanged::class => 'handlePasswordChanged',
            TwoFactorEnabled::class => 'handleTwoFactorEnabled',
            TwoFactorDisabled::class => 'handleTwoFactorDisabled',
        ];
    }
}
