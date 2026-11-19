<?php

namespace App\Domain\Identity\Tests\Feature\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_login_locks_identity_after_max_failures(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'password123',
        ])
            ->assertStatus(429)
            ->assertJsonPath('status', 'error')
            ->assertHeader('Retry-After');
    }

    public function test_failed_attempts_from_other_ip_do_not_lock_identity(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
                ->postJson('/api/v1/auth/login', [
                    'email' => 'victim@example.com',
                    'password' => 'wrong-password',
                ])->assertUnauthorized();
        }

        // Same identity from a different IP keeps its own quota — the core
        // false-positive fix for users behind shared NAT/CGNAT.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'password123',
            ])->assertOk();
    }

    public function test_failed_attempts_on_other_email_do_not_lock_identity(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'attacker@example.com',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_ip_backstop_locks_cross_email_brute_force(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => "target{$i}@example.com",
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'fresh@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_validation_failure_does_not_consume_quota(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'password123',
        ])->assertOk();
    }

    public function test_successful_logins_do_not_consume_quota(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'victim@example.com',
                'password' => 'password123',
            ])->assertOk();
        }
    }

    public function test_register_attempts_do_not_lock_login(): void
    {
        User::factory()->create([
            'email' => 'victim@example.com',
            'password' => Hash::make('password123'),
        ]);

        // Register has its own throttle key (auth-register prefix); five
        // registrations must not consume the login identity quota.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/register', [
                'name' => "User {$i}",
                'email' => "new{$i}@example.com",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'victim@example.com',
            'password' => 'password123',
        ])->assertOk();
    }
}
