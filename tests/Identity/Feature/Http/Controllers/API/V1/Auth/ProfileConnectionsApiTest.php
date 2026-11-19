<?php

namespace App\Domain\Identity\Tests\Feature\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Support\DiscordOAuthState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class ProfileConnectionsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        config([
            'services.discord.client_id' => 'test-discord-client-id',
            'services.discord.client_secret' => 'test-discord-client-secret',
            'services.discord.redirect' => 'https://monolith.test/apps/auth/discord/callback',
            'services.spa.url' => 'https://spa.test',
            'app.url' => 'https://monolith.test',
        ]);
    }

    #[Test]
    public function guest_cannot_read_connections(): void
    {
        $this->getJson('/api/v1/profile/connections')->assertUnauthorized();
    }

    #[Test]
    public function authenticated_user_reads_disconnected_snapshot(): void
    {
        $user = $this->makeUser();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/profile/connections')
            ->assertOk()
            ->assertJsonPath('data.providers.0.key', 'discord')
            ->assertJsonPath('data.providers.0.connected', false)
            ->assertJsonPath('data.providers.0.oauth_configured', true)
            ->assertJsonPath('data.providers.0.supports_sync', true)
            ->assertJsonPath('data.providers.0.connections', []);
    }

    #[Test]
    public function authenticated_user_reads_connected_snapshot(): void
    {
        $user = $this->makeUser();
        $account = new IdentityOAuthAccount;
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = '999';
        $account->forceFill([
            'access_token' => 'test-access',
            'refresh_token' => 'test-refresh',
            'connections_snapshot' => [
                ['type' => 'youtube', 'id' => 'UC123', 'name' => 'Test', 'verified' => true],
            ],
            'connections_synced_at' => now(),
        ])->save();

        Http::fake();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/profile/connections')
            ->assertOk()
            ->assertJsonPath('data.providers.0.key', 'discord')
            ->assertJsonPath('data.providers.0.connected', true)
            ->assertJsonPath('data.providers.0.supports_sync', true)
            ->assertJsonPath('data.providers.0.connections.0.name', 'Test')
            ->assertJsonPath('data.providers.0.connections.0.verified', true);

        Http::assertNothingSent();
    }

    #[Test]
    public function disconnect_removes_discord_account(): void
    {
        $user = $this->makeUser();
        $account = new IdentityOAuthAccount;
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = '999';
        $account->forceFill([
            'access_token' => 'test-access',
            'connections_snapshot' => [],
            'connections_synced_at' => now(),
        ])->save();

        Http::fake();

        $this->actingAsJwt($user)
            ->deleteJson('/api/v1/profile/connections/discord')
            ->assertOk()
            ->assertJsonPath('message', __('api/identity.connections.disconnected'));

        $this->assertDatabaseMissing('identity_oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Discord->value,
        ]);
    }

    #[Test]
    public function authorize_url_requires_spa_url(): void
    {
        config(['services.spa.url' => '']);
        $user = $this->makeUser();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/auth/discord/authorize-url?intent=profile&return_path=/apps/profile/connections')
            ->assertStatus(503);
    }

    #[Test]
    public function authorize_url_rejects_non_apps_return_path(): void
    {
        $user = $this->makeUser();

        $this->actingAsJwt($user)
            ->getJson('/api/v1/auth/discord/authorize-url?intent=profile&return_path=/login')
            ->assertStatus(422);
    }

    #[Test]
    public function authorize_url_returns_discord_url_with_return_to_in_state(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAsJwt($user)
            ->getJson('/api/v1/auth/discord/authorize-url?intent=profile&return_path=/apps/profile/connections')
            ->assertOk();

        $url = (string) $response->json('data.url');
        $this->assertStringContainsString('https://discord.com/api/oauth2/authorize', $url);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $payload = DiscordOAuthState::decode((string) $query['state']);
        $this->assertSame((string) $user->id, $payload['user_id']);
        $this->assertSame('profile', $payload['intent']);
        $this->assertSame('https://spa.test/apps/profile/connections', $payload['return_to']);
    }

    #[Test]
    public function sync_without_account_returns_not_connected(): void
    {
        $user = $this->makeUser();

        $this->actingAsJwt($user)
            ->postJson('/api/v1/profile/connections/discord/sync')
            ->assertStatus(422)
            ->assertJsonPath('message', __('api/identity.oauth.discord_not_connected'));
    }

    #[Test]
    public function sync_refreshes_discord_snapshot(): void
    {
        $user = $this->makeUser();
        $account = new IdentityOAuthAccount;
        $account->user_id = $user->id;
        $account->provider = OAuthProvider::Discord;
        $account->provider_user_id = '999';
        $account->forceFill([
            'access_token' => 'test-access',
            'refresh_token' => 'test-refresh',
            'connections_snapshot' => [],
            'connections_synced_at' => now()->subHours(2),
        ])->save();

        Http::fake([
            'discord.com/api/users/@me/connections' => Http::response([
                [
                    'type' => 'youtube',
                    'id' => 'UC999',
                    'name' => 'Synced Channel',
                    'verified' => true,
                ],
            ]),
        ]);

        $this->actingAsJwt($user)
            ->postJson('/api/v1/profile/connections/discord/sync')
            ->assertOk()
            ->assertJsonPath('message', __('api/identity.connections.synced'))
            ->assertJsonPath('data.providers.0.connected', true)
            ->assertJsonPath('data.providers.0.connections.0.id', 'UC999')
            ->assertJsonPath('data.providers.0.connections.0.name', 'Synced Channel');

        $account->refresh();
        $this->assertNotNull($account->connections_synced_at);
        $this->assertTrue($account->connections_synced_at->greaterThan(now()->subMinute()));
    }

    #[Test]
    public function sync_unknown_provider_returns_not_found(): void
    {
        $user = $this->makeUser();

        $this->actingAsJwt($user)
            ->postJson('/api/v1/profile/connections/google/sync')
            ->assertNotFound();
    }

    private function makeUser(): User
    {
        return User::query()->create([
            'name' => 'Connections API',
            'email' => 'conn-api-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    /**
     * @return array{Authorization: string}
     */
    private function actingAsJwt(User $user): static
    {
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
