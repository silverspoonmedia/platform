<?php

namespace App\Domain\Identity\Tests\Feature\Http\Controllers\Web;

use App\Domain\Identity\Tests\Feature\FeatureTestCase;

class DiscordOAuthCallbackWebTest extends FeatureTestCase
{
    private const SPA = 'https://spa.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.spa.url' => self::SPA,
            'app.url' => 'https://monolith.test',
        ]);
    }

    public function test_callback_without_code_or_state_redirects_to_spa_connections_with_error(): void
    {
        $this->get('/apps/auth/discord/callback')
            ->assertRedirect(self::SPA.'/apps/profile/connections?discord=error');
    }

    public function test_callback_with_oauth_error_redirects_to_spa_connections_as_denied(): void
    {
        $this->get('/apps/auth/discord/callback?error=access_denied')
            ->assertRedirect(self::SPA.'/apps/profile/connections?discord=denied');
    }

    public function test_callback_with_invalid_state_redirects_to_spa_connections_with_error(): void
    {
        $this->get('/apps/auth/discord/callback?code=abc&state=not-valid')
            ->assertRedirect(self::SPA.'/apps/profile/connections?discord=error');
    }
}
