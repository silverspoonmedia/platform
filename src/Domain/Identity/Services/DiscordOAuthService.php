<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class DiscordOAuthService
{
    private const AUTHORIZE_URL = 'https://discord.com/api/oauth2/authorize';

    private const TOKEN_URL = 'https://discord.com/api/oauth2/token';

    private const REVOKE_URL = 'https://discord.com/api/oauth2/token/revoke';

    private const SCOPES = 'identify connections';

    public function isConfigured(): bool
    {
        return $this->clientId() !== '' && $this->clientSecret() !== '' && $this->redirectUri() !== '';
    }

    public function authorizeUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new IdentityOAuthException(__('api/identity.oauth.discord_not_configured'), 503);
        }

        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPES,
            'state' => $state,
            'prompt' => 'consent',
        ]);

        return self::AUTHORIZE_URL.'?'.$query;
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, provider_user_id: ?string}
     */
    public function exchangeCode(string $code): array
    {
        if (! $this->isConfigured()) {
            throw new IdentityOAuthException(__('api/identity.oauth.discord_not_configured'), 503);
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(self::TOKEN_URL, [
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $this->redirectUri(),
                ]);
        } catch (RequestException $e) {
            throw new IdentityOAuthException(__('api/identity.oauth.token_exchange_failed'), 422, $e);
        }

        if (! $response->successful()) {
            throw new IdentityOAuthException(__('api/identity.oauth.token_exchange_failed'), 422);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $accessToken = (string) ($body['access_token'] ?? '');
        if ($accessToken === '') {
            throw new IdentityOAuthException(__('api/identity.oauth.token_exchange_failed'), 422);
        }

        $providerUserId = $this->fetchDiscordUserId($accessToken);

        return [
            'access_token' => $accessToken,
            'refresh_token' => isset($body['refresh_token']) ? (string) $body['refresh_token'] : null,
            'expires_in' => isset($body['expires_in']) ? (int) $body['expires_in'] : null,
            'scope' => isset($body['scope']) ? (string) $body['scope'] : null,
            'provider_user_id' => $providerUserId,
        ];
    }

    /**
     * @return array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string}
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        if (! $this->isConfigured()) {
            throw new IdentityOAuthException(__('api/identity.oauth.discord_not_configured'), 503);
        }

        try {
            $response = Http::asForm()
                ->timeout(15)
                ->post(self::TOKEN_URL, [
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                ]);
        } catch (RequestException $e) {
            throw new IdentityOAuthException(__('api/identity.oauth.token_refresh_failed'), 502, $e);
        }

        if (! $response->successful()) {
            throw new IdentityOAuthException(__('api/identity.oauth.token_refresh_failed'), 502);
        }

        /** @var array<string, mixed> $body */
        $body = $response->json() ?? [];
        $accessToken = (string) ($body['access_token'] ?? '');
        if ($accessToken === '') {
            throw new IdentityOAuthException(__('api/identity.oauth.token_refresh_failed'), 502);
        }

        return [
            'access_token' => $accessToken,
            'refresh_token' => isset($body['refresh_token']) ? (string) $body['refresh_token'] : $refreshToken,
            'expires_in' => isset($body['expires_in']) ? (int) $body['expires_in'] : null,
            'scope' => isset($body['scope']) ? (string) $body['scope'] : null,
        ];
    }

    public function revokeToken(string $token): void
    {
        if (! $this->isConfigured() || $token === '') {
            return;
        }

        try {
            Http::asForm()
                ->timeout(10)
                ->post(self::REVOKE_URL, [
                    'client_id' => $this->clientId(),
                    'client_secret' => $this->clientSecret(),
                    'token' => $token,
                ]);
        } catch (RequestException) {
            // Best-effort revoke.
        }
    }

    private function fetchDiscordUserId(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(10)
                ->get('https://discord.com/api/users/@me');
        } catch (RequestException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $id = $response->json('id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    private function clientId(): string
    {
        return (string) config('services.discord.client_id', '');
    }

    private function clientSecret(): string
    {
        return (string) config('services.discord.client_secret', '');
    }

    private function redirectUri(): string
    {
        return (string) config('services.discord.redirect', '');
    }
}
