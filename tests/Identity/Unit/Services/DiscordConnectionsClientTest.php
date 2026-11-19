<?php

namespace App\Domain\Identity\Tests\Unit\Services;

use App\Domain\Identity\Services\DiscordConnectionsClient;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DiscordConnectionsClientTest extends TestCase
{
    private DiscordConnectionsClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = app(DiscordConnectionsClient::class);
    }

    #[Test]
    public function normalize_keeps_all_types_and_safe_fields(): void
    {
        $normalized = $this->client->normalize([
            [
                'type' => 'youtube',
                'id' => 'UC123',
                'name' => 'YT Name',
                'verified' => true,
                'revoked' => false,
                'friend_sync' => true,
                'visibility' => 1,
                'access_token' => 'secret',
            ],
            [
                'type' => 'github',
                'id' => 'gh-1',
                'name' => 'octocat',
                'verified' => true,
            ],
        ]);

        $this->assertCount(2, $normalized);
        $this->assertSame('github', $normalized[1]['type']);
        $this->assertSame('octocat', $normalized[1]['name']);
        $this->assertArrayNotHasKey('access_token', $normalized[0]);
        $this->assertTrue($normalized[0]['friend_sync']);
    }
}
