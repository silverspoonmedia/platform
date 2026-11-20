<?php

namespace App\Domain\Identity\Tests\Unit\Services;

use App\Domain\Identity\Enums\OAuthProvider;
use App\Domain\Identity\Models\IdentityOAuthAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\UserOAuthAccountService;
use App\Domain\Identity\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class UserOAuthAccountServiceTest extends TestCase
{
    private UserOAuthAccountService $oauthAccounts;

    protected function setUp(): void
    {
        parent::setUp();
        $this->oauthAccounts = app(UserOAuthAccountService::class);
    }

    #[Test]
    public function connect_discord_returns_identity_oauth_account_with_connections_snapshot(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'discord.com/api/users/@me/connections' => Http::response([
                [
                    'type' => 'youtube',
                    'id' => 'UC1234567890',
                    'name' => 'Test Channel',
                    'verified' => true,
                ],
                [
                    'type' => 'twitch',
                    'id' => '999888',
                    'name' => 'TestTwitch',
                    'verified' => false,
                ],
            ], 200),
        ]);

        $account = $this->oauthAccounts->connectDiscord($user, [
            'access_token' => 'discord-access-token',
            'refresh_token' => 'discord-refresh-token',
            'expires_in' => 3600,
            'scope' => 'identify connections',
            'provider_user_id' => 'discord-user-123',
        ]);

        $this->assertInstanceOf(IdentityOAuthAccount::class, $account);
        $this->assertSame('discord-user-123', $account->provider_user_id);
        $this->assertSame(OAuthProvider::Discord, $account->provider);
        $this->assertNotNull($account->connections_synced_at);
        $this->assertCount(2, $account->connections_snapshot);
        $this->assertSame('youtube', $account->connections_snapshot[0]['type']);
        $this->assertSame('UC1234567890', $account->connections_snapshot[0]['id']);

        $this->assertDatabaseHas('identity_oauth_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::Discord->value,
            'provider_user_id' => 'discord-user-123',
        ]);
    }
}
