<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Exceptions\IdentityOAuthException;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;

class UserOAuthAccountService
{
    public function __construct(
        private readonly DiscordOAuthService $discordOAuth,
        private readonly DiscordConnectionsClient $connectionsClient,
    ) {}

    public function discordAccountFor(User $user): ?IdentityOAuthAccount
    {
        /** @var IdentityOAuthAccount|null $account */
        $account = IdentityOAuthAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::Discord->value)
            ->first();

        return $account;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function connectionsSnapshotFor(User $user): array
    {
        $account = $this->discordAccountFor($user);
        if ($account === null) {
            return [];
        }

        $snapshot = $account->connections_snapshot;

        return is_array($snapshot) ? $snapshot : [];
    }

    public function isConnectionsSnapshotFresh(User $user): bool
    {
        $account = $this->discordAccountFor($user);
        if ($account === null || $account->connections_synced_at === null) {
            return false;
        }

        $maxAgeMinutes = max(1, (int) setting_config(
            'identity.discord.connections_snapshot_max_age_minutes',
            (int) config('services.discord.connections_snapshot_max_age_minutes', 60),
        ));

        return $account->connections_synced_at->gt(now()->subMinutes($maxAgeMinutes));
    }

    /**
     * @param  array{access_token: string, refresh_token: ?string, expires_in: ?int, scope: ?string, provider_user_id: ?string}  $tokenPayload
     */
    public function connectDiscord(User $user, array $tokenPayload): IdentityOAuthAccount
    {
        $expiresAt = isset($tokenPayload['expires_in'])
          ? now()->addSeconds((int) $tokenPayload['expires_in'])
          : null;

        $providerUserId = (string) ($tokenPayload['provider_user_id'] ?? '');
        if ($providerUserId === '') {
            throw new IdentityOAuthException(__('api/identity.oauth.discord_user_missing'), 422);
        }

        /** @var IdentityOAuthAccount $account */
        $account = IdentityOAuthAccount::query()->firstOrNew([
            'user_id' => $user->id,
            'provider' => OAuthProvider::Discord,
        ]);

        $account->forceFill([
            'provider_user_id' => $providerUserId,
            'access_token' => $tokenPayload['access_token'],
            'refresh_token' => $tokenPayload['refresh_token'],
            'token_expires_at' => $expiresAt,
            'scopes' => $tokenPayload['scope'] ?? null,
        ])->save();

        $this->syncDiscordConnections($user, $account);

        return $account->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function syncDiscordConnections(User $user, ?IdentityOAuthAccount $account = null): array
    {
        $account ??= $this->discordAccountFor($user);
        if ($account === null) {
            throw new IdentityOAuthException(__('api/identity.oauth.discord_not_connected'), 422);
        }

        $accessToken = $this->ensureFreshAccessToken($account);
        $connections = $this->connectionsClient->fetchConnections($accessToken);

        $account->forceFill([
            'connections_snapshot' => $connections,
            'connections_synced_at' => now(),
        ])->save();

        return $connections;
    }

    public function disconnectDiscord(User $user): void
    {
        $account = $this->discordAccountFor($user);
        if ($account === null) {
            return;
        }

        $token = $account->access_token;
        if (is_string($token) && $token !== '') {
            $this->discordOAuth->revokeToken($token);
        }

        $account->delete();
    }

    private function ensureFreshAccessToken(IdentityOAuthAccount $account): string
    {
        if (! $account->isTokenExpired()) {
            $token = $account->access_token;
            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        $refresh = $account->refresh_token;
        if (! is_string($refresh) || $refresh === '') {
            throw new IdentityOAuthException(__('api/identity.oauth.token_refresh_failed'), 401);
        }

        $payload = $this->discordOAuth->refreshAccessToken($refresh);
        $expiresAt = isset($payload['expires_in'])
          ? now()->addSeconds((int) $payload['expires_in'])
          : null;

        $account->forceFill([
            'access_token' => $payload['access_token'],
            'refresh_token' => $payload['refresh_token'] ?? $refresh,
            'token_expires_at' => $expiresAt,
            'scopes' => $payload['scope'] ?? $account->scopes,
        ])->save();

        return $payload['access_token'];
    }
}
