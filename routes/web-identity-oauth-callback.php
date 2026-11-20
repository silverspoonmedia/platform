<?php

use App\Domain\Identity\Http\Controllers\Web\Auth\DiscordOAuthController;
use Illuminate\Support\Facades\Route;

/*
| Discord OAuth callback must be reachable without a Blade JWT cookie.
| SPA starts OAuth via GET /api/v1/auth/discord/authorize-url (Bearer); Discord
| returns here. Identity is the encrypted state, not the web session.
| Path stays /apps/auth/discord/callback (DISCORD_REDIRECT_URI).
*/
Route::prefix('apps')->group(function (): void {
    Route::get('auth/discord/callback', [DiscordOAuthController::class, 'callback'])
        ->name('auth.discord.callback');
});
