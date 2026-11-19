<?php

namespace App\Domain\Identity\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DiscordOAuthService;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Http\Controllers\Controller;
use App\Support\Presentation\DatePresenter;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ProfileConnectionsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly UserOAuthAccountService $oauthAccounts,
        private readonly DiscordOAuthService $discordOAuth,
    ) {}

    public function show(): JsonResponse
    {
        $this->refreshStaleDiscordSnapshot($this->actor());

        return $this->successResponse(
            $this->presentProviders($this->actor()),
            __('api/identity.connections.show'),
        );
    }

    public function sync(string $oauth_provider): JsonResponse
    {
        $resolved = $this->resolveProvider($oauth_provider);

        match ($resolved) {
            OAuthProvider::Discord => $this->oauthAccounts->syncDiscordConnections($this->actor()),
        };

        return $this->successResponse(
            $this->presentProviders($this->actor()),
            __('api/identity.connections.synced'),
        );
    }

    public function disconnect(string $oauth_provider): JsonResponse
    {
        $resolved = $this->resolveProvider($oauth_provider);

        match ($resolved) {
            OAuthProvider::Discord => $this->oauthAccounts->disconnectDiscord($this->actor()),
        };

        return $this->successResponse(null, __('api/identity.connections.disconnected'));
    }

    private function resolveProvider(string $provider): OAuthProvider
    {
        $resolved = OAuthProvider::tryFrom($provider);
        if ($resolved === null) {
            abort(404);
        }

        return $resolved;
    }

    private function refreshStaleDiscordSnapshot(User $user): void
    {
        $account = $this->oauthAccounts->discordAccountFor($user);
        if ($account === null || $this->oauthAccounts->isConnectionsSnapshotFresh($user)) {
            return;
        }

        try {
            $this->oauthAccounts->syncDiscordConnections($user);
        } catch (\Throwable) {
            // Keep stale snapshot when Discord API is unavailable.
        }
    }

    /**
     * @return array{providers: list<array<string, mixed>>}
     */
    private function presentProviders(User $user): array
    {
        $providers = [];

        foreach (OAuthProvider::cases() as $provider) {
            $providers[] = match ($provider) {
                OAuthProvider::Discord => $this->presentDiscord($user),
            };
        }

        return ['providers' => $providers];
    }

    /**
     * @return array{
     *     key: string,
     *     connected: bool,
     *     oauth_configured: bool,
     *     supports_sync: bool,
     *     synced_at: string|null,
     *     synced_at_formatted: string|null,
     *     synced_at_human: string|null,
     *     connections: list<array{type: string, id: string|null, name: string|null, verified: bool, revoked: bool}>
     * }
     */
    private function presentDiscord(User $user): array
    {
        $account = $this->oauthAccounts->discordAccountFor($user);
        [$syncedAt, $syncedAtFormatted, $syncedAtHuman] = DatePresenter::pack($account?->connections_synced_at);

        return [
            'key' => OAuthProvider::Discord->value,
            'connected' => $account !== null,
            'oauth_configured' => $this->discordOAuth->isConfigured(),
            'supports_sync' => OAuthProvider::Discord->supportsSync(),
            'synced_at' => $syncedAt,
            'synced_at_formatted' => $syncedAtFormatted,
            'synced_at_human' => $syncedAtHuman,
            'connections' => $account === null
                ? []
                : $this->presentConnections($this->oauthAccounts->connectionsSnapshotFor($user)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @return list<array{type: string, id: string|null, name: string|null, verified: bool, revoked: bool}>
     */
    private function presentConnections(array $snapshot): array
    {
        $rows = [];

        foreach ($snapshot as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rows[] = [
                'type' => (string) ($row['type'] ?? ''),
                'id' => isset($row['id']) ? (string) $row['id'] : null,
                'name' => isset($row['name']) ? (string) $row['name'] : null,
                'verified' => (bool) ($row['verified'] ?? false),
                'revoked' => (bool) ($row['revoked'] ?? false),
            ];
        }

        return $rows;
    }

    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        return $user;
    }
}
