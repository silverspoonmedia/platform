<?php

namespace App\Domain\Identity\Tests\Unit\Support;

use App\Domain\Identity\Support\DiscordOAuthState;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordOAuthStateTest extends TestCase
{
    #[Test]
    public function encode_decode_round_trips_return_to(): void
    {
        $state = DiscordOAuthState::encode(
            'user-1',
            'profile',
            'https://spa.test/apps/profile/connections',
        );

        $payload = DiscordOAuthState::decode($state);

        $this->assertSame('user-1', $payload['user_id']);
        $this->assertSame('profile', $payload['intent']);
        $this->assertSame('https://spa.test/apps/profile/connections', $payload['return_to']);
    }
}
