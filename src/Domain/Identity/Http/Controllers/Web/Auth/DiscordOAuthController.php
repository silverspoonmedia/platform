<?php

namespace App\Domain\Identity\Http\Controllers\Web\Auth;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Identity\Support\DiscordOAuthState;
use App\Domain\Identity\Support\OAuthReturnUrl;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiscordOAuthController extends Controller
{
    public function __construct(
        private readonly DiscordOAuthService $discordOAuth,
        private readonly UserOAuthAccountService $oauthAccounts,
    ) {}

    public function redirect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $returnTo = OAuthReturnUrl::sanitize(
            is_string($request->query('return_to')) ? $request->query('return_to') : null,
        );

        $state = DiscordOAuthState::encode((string) $user->id, $returnTo);

        return redirect()->away($this->discordOAuth->authorizeUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $state = (string) $request->query('state', '');
        $oauthError = $request->query('error');

        $payload = null;
        if ($state !== '') {
            try {
                $payload = DiscordOAuthState::decode($state);
            } catch (\InvalidArgumentException) {
                $payload = null;
            }
        }

        if ($oauthError !== null) {
            return $this->redirectAfterCallback($payload, 'denied');
        }

        if ($code === '' || $payload === null) {
            return $this->redirectAfterCallback($payload, 'error');
        }

        $user = $this->userFromCallback($request, $payload);

        try {
            $tokenPayload = $this->discordOAuth->exchangeCode($code);
            $this->oauthAccounts->connectDiscord($user, $tokenPayload);
        } catch (IdentityOAuthException) {
            return $this->redirectAfterCallback($payload, 'error');
        }

        return $this->redirectAfterCallback($payload, 'connected');
    }

    /**
     * @param  array{user_id: string, return_to?: ?string}  $payload
     */
    private function userFromCallback(Request $request, array $payload): User
    {
        $sessionUser = $request->user();
        if ($sessionUser instanceof User && (string) $sessionUser->id !== $payload['user_id']) {
            abort(403);
        }

        /** @var User|null $user */
        $user = User::query()->find($payload['user_id']);
        if ($user === null) {
            abort(403);
        }

        return $user;
    }

    /**
     * @param  array{user_id: string, return_to?: ?string}|null  $payload
     */
    private function redirectAfterCallback(?array $payload, string $spaStatus): RedirectResponse
    {
        $returnTo = is_string($payload['return_to'] ?? null)
            ? OAuthReturnUrl::sanitize((string) $payload['return_to'])
            : null;

        if ($returnTo !== null) {
            return redirect()->away(OAuthReturnUrl::withDiscordStatus($returnTo, $spaStatus));
        }

        // Blade is gone: every fallback lands on an SPA path, still carrying the status.
        return redirect()->away(OAuthReturnUrl::withDiscordStatus(OAuthReturnUrl::fromSpaPath('/apps/profile/connections'), $spaStatus));
    }
}
