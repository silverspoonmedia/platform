<?php

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Http\Controllers\API\V1\AdminController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\AuthController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\DiscordOAuthController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\EmailVerificationController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\LocaleController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\PasswordController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\ProfileConnectionsController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\SpaCsrfController;
use App\Domain\Identity\Http\Controllers\API\V1\Auth\TwoFactorController;
use App\Domain\Identity\Http\Controllers\API\V1\LookupController;
use App\Domain\Identity\Http\Controllers\API\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1,auth-register')
        ->name('register');
    // Login throttling is identity-scoped and failure-only, enforced inside
    // AuthService via AuthLoginThrottle — see config/auth.php login_throttle.
    Route::post('login', [AuthController::class, 'login'])
        ->name('login');
    Route::post('two-factor/login', [TwoFactorController::class, 'login'])
        ->name('two-factor.login');
    Route::post('forgot-password', [PasswordController::class, 'forgot'])
        ->middleware('throttle:3,1')
        ->name('password.forgot');
    Route::post('reset-password', [PasswordController::class, 'reset'])
        ->middleware('throttle:3,1')
        ->name('password.reset');

    Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::middleware('auth:api')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('locale', [LocaleController::class, 'update'])->name('locale');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'send'])
            ->middleware('throttle:6,1')
            ->name('verification.send');
        Route::put('password', [PasswordController::class, 'update'])->name('password.update');
        Route::post('two-factor/setup', [TwoFactorController::class, 'startSetup'])
            ->middleware('throttle:3,1')
            ->name('two-factor.setup');
        Route::post('two-factor/confirm', [TwoFactorController::class, 'confirmSetup'])
            ->middleware('throttle:3,1')
            ->name('two-factor.confirm');
        Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])
            ->middleware('throttle:3,1')
            ->name('two-factor.disable');
        Route::get('discord/authorize-url', [DiscordOAuthController::class, 'authorizeUrl'])
            ->middleware('throttle:10,1')
            ->name('discord.authorize-url');
    });

    // Single refresh inside AuthService (no jwt.refresh middleware — that layer
    // would double-refresh and race the blacklist before the controller returns).
    Route::post('refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:30,1')
        ->name('refresh');
});

// Double-submit CSRF nonce for the SPA's cross-site cookie auth. Public GET;
// the token carries no privilege and is verified by VerifySpaCsrf on mutation.
Route::get('csrf', [SpaCsrfController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('csrf.show');

Route::middleware('auth:api')->group(function (): void {
    Route::get('profile/connections', [ProfileConnectionsController::class, 'show'])
        ->name('profile.connections.show');
    Route::post('profile/connections/{oauth_provider}/sync', [ProfileConnectionsController::class, 'sync'])
        ->whereIn('oauth_provider', OAuthProvider::values())
        ->middleware('throttle:10,1')
        ->name('profile.connections.sync');
    Route::delete('profile/connections/{oauth_provider}', [ProfileConnectionsController::class, 'disconnect'])
        ->whereIn('oauth_provider', OAuthProvider::values())
        ->name('profile.connections.disconnect');

    Route::get('users/assignable-roles', [UserController::class, 'assignableRoles'])
        ->name('users.assignable-roles');

    Route::apiResource('users', UserController::class);

    Route::prefix('lookups')->name('lookups.')->group(function (): void {
        Route::get('users', [LookupController::class, 'users'])->name('users');
        Route::get('roles', [LookupController::class, 'roles'])->name('roles');
    });
});

Route::middleware(['auth:api', 'permission:view user'])
    ->get('admin/ping', [AdminController::class, 'ping'])
    ->name('admin.ping');
