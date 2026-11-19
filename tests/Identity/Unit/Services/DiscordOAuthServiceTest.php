<?php

namespace App\Domain\Identity\Tests\Unit\Services;

use App\Domain\Identity\Services\DiscordOAuthService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordOAuthServiceTest extends TestCase
{
    private DiscordOAuthService $discordOAuth;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discordOAuth = app(DiscordOAuthService::class);
    }

    #[Test]
    public function is_configured_is_false_when_credentials_missing(): void
    {
        config([
            'services.discord.client_id' => '',
            'services.discord.client_secret' => '',
            'services.discord.redirect' => 'https://example.test/apps/auth/discord/callback',
        ]);

        $this->assertFalse($this->discordOAuth->isConfigured());
    }

    #[Test]
    public function is_configured_is_false_when_redirect_missing(): void
    {
        config([
            'services.discord.client_id' => 'client-id',
            'services.discord.client_secret' => 'client-secret',
            'services.discord.redirect' => '',
        ]);

        $this->assertFalse($this->discordOAuth->isConfigured());
    }

    #[Test]
    public function is_configured_is_true_when_credentials_and_redirect_present(): void
    {
        config([
            'services.discord.client_id' => 'client-id',
            'services.discord.client_secret' => 'client-secret',
            'services.discord.redirect' => 'https://example.test/apps/auth/discord/callback',
        ]);

        $this->assertTrue($this->discordOAuth->isConfigured());
    }

    #[Test]
    public function authorize_url_uses_configured_redirect_uri(): void
    {
        config([
            'services.discord.client_id' => 'client-id',
            'services.discord.client_secret' => 'client-secret',
            'services.discord.redirect' => 'https://monolith.test/apps/auth/discord/callback',
        ]);

        $url = $this->discordOAuth->authorizeUrl('signed-state');

        $this->assertStringContainsString('client_id=client-id', $url);
        $this->assertStringContainsString(
            'redirect_uri='.rawurlencode('https://monolith.test/apps/auth/discord/callback'),
            $url,
        );
    }

    #[Test]
    public function services_config_defaults_redirect_from_app_url_when_env_empty(): void
    {
        $this->assertSame(
            rtrim((string) config('app.url'), '/').'/apps/auth/discord/callback',
            config('services.discord.redirect'),
        );
    }
}
