<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Domain\Identity\Exceptions\IdentityOAuthException;
use App\Domain\Identity\Support\OAuthReturnUrl;
use App\Domain\Identity\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OAuthReturnUrlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.url' => 'https://monolith.test',
            'services.spa.url' => 'https://spa.test',
        ]);
    }

    #[Test]
    public function from_spa_path_prefixes_configured_origin(): void
    {
        $this->assertSame(
            'https://spa.test/apps/profile/connections',
            OAuthReturnUrl::fromSpaPath('/apps/profile/connections'),
        );
    }

    #[Test]
    public function sanitize_rejects_foreign_origin(): void
    {
        $this->assertNull(OAuthReturnUrl::sanitize('https://evil.test/apps/profile/connections'));
    }

    #[Test]
    public function sanitize_allows_app_url(): void
    {
        $this->assertSame(
            'https://monolith.test/apps/profile/connections',
            OAuthReturnUrl::sanitize('https://monolith.test/apps/profile/connections'),
        );
    }

    #[Test]
    public function from_spa_path_rejects_non_apps_path(): void
    {
        $this->expectException(IdentityOAuthException::class);
        OAuthReturnUrl::fromSpaPath('/login');
    }

    #[Test]
    public function from_spa_path_requires_spa_url(): void
    {
        config(['services.spa.url' => '']);
        $this->expectException(IdentityOAuthException::class);
        OAuthReturnUrl::fromSpaPath('/apps/profile/connections');
    }

    #[Test]
    public function with_discord_status_sets_query(): void
    {
        $this->assertSame(
            'https://spa.test/apps/profile/connections?discord=connected',
            OAuthReturnUrl::withDiscordStatus('https://spa.test/apps/profile/connections', 'connected'),
        );
    }
}
