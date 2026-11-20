<?php

namespace App\Domain\Identity\Tests\Unit\Services\Auth;

use App\Domain\Identity\Exceptions\Auth\InvalidCredentialsException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Domain\Identity\Tests\TestCase;
use App\Support\Testing\Concerns\SeedsRolesAndPermissions;
use Illuminate\Support\Facades\Hash;

class AuthServiceTest extends TestCase
{
    use SeedsRolesAndPermissions;

    private AuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
        $this->service = app(AuthService::class);
    }

    public function test_register_persists_user_assigns_role_and_returns_token(): void
    {
        $payload = $this->service->register([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $user = User::query()->where('email', 'jane@example.com')->first();
        $this->assertTrue($user->hasRole('User'));
        $this->assertNotEmpty($payload['token']);
        $this->assertSame('jane@example.com', $payload['user']['email']);
    }

    public function test_login_throws_when_credentials_are_invalid(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->expectException(InvalidCredentialsException::class);

        $this->service->login([
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);
    }

    public function test_login_returns_token_for_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = $this->service->login([
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $this->assertNotEmpty($payload['token']);
        $this->assertSame('user@example.com', $payload['user']['email']);
        $this->assertFalse($payload['remember']);
        $this->assertSame((int) config('jwt.ttl', 60) * 60, $payload['expires_in']);
    }

    public function test_login_with_remember_me_uses_extended_ttl(): void
    {
        config(['jwt.remember_ttl' => 120]);

        User::factory()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('password123'),
        ]);

        $payload = $this->service->login([
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ]);

        $this->assertTrue($payload['remember']);
        $this->assertSame(120 * 60, $payload['expires_in']);
    }

    public function test_remember_claim_does_not_leak_across_requests(): void
    {
        config(['jwt.ttl' => 60, 'jwt.remember_ttl' => 120]);

        User::factory()->create([
            'email' => 'leak-test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $rememberPayload = $this->service->login([
            'email' => 'leak-test@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ]);

        $this->assertTrue($rememberPayload['remember']);
        $this->assertSame(120 * 60, $rememberPayload['expires_in']);

        $normalPayload = $this->service->login([
            'email' => 'leak-test@example.com',
            'password' => 'password123',
            'remember_me' => false,
        ]);

        $this->assertFalse($normalPayload['remember']);
        $this->assertSame(60 * 60, $normalPayload['expires_in']);
    }

    public function test_refresh_preserves_remember_flag_from_original_token(): void
    {
        config(['jwt.ttl' => 60, 'jwt.remember_ttl' => 120, 'jwt.refresh_ttl' => 20160]);

        User::factory()->create([
            'email' => 'refresh-remember@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginPayload = $this->service->login([
            'email' => 'refresh-remember@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ]);

        $this->assertTrue($loginPayload['remember']);

        $refreshed = $this->service->refreshFromToken($loginPayload['token']);

        $this->assertTrue($refreshed['remember'], 'refresh should preserve remember=true from original token');
        $this->assertSame(120 * 60, $refreshed['expires_in']);
        $this->assertNotSame($loginPayload['token'], $refreshed['token']);
    }

    public function test_refresh_preserves_non_remember_flag_from_original_token(): void
    {
        config(['jwt.ttl' => 60, 'jwt.remember_ttl' => 120, 'jwt.refresh_ttl' => 20160]);

        User::factory()->create([
            'email' => 'refresh-normal@example.com',
            'password' => Hash::make('password123'),
        ]);

        $loginPayload = $this->service->login([
            'email' => 'refresh-normal@example.com',
            'password' => 'password123',
            'remember_me' => false,
        ]);

        $this->assertFalse($loginPayload['remember']);

        $refreshed = $this->service->refreshFromToken($loginPayload['token']);

        $this->assertFalse($refreshed['remember'], 'refresh should preserve remember=false from original token');
        $this->assertSame(60 * 60, $refreshed['expires_in']);
    }
}
