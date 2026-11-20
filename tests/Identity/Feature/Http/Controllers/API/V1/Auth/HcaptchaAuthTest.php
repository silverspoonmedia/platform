<?php

namespace App\Domain\Identity\Tests\Feature\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Tests\Feature\FeatureTestCase;
use App\Support\Testing\Concerns\SeedsRolesAndPermissions;
use Illuminate\Support\Facades\Http;

class HcaptchaAuthTest extends FeatureTestCase
{
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();

        config([
            'hcaptcha.enabled' => true,
            'hcaptcha.secret' => 'test-secret',
            'hcaptcha.sitekey' => 'test-sitekey',
        ]);
    }

    public function test_login_requires_captcha_token_when_enabled(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['h-captcha-response']);
    }

    public function test_login_proceeds_past_captcha_with_valid_token(): void
    {
        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
            'h-captcha-response' => 'valid-captcha-token',
        ])
            ->assertUnauthorized()
            ->assertJsonMissingValidationErrors(['h-captcha-response']);
    }

    public function test_register_requires_captcha_token_when_enabled(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['h-captcha-response']);
    }

    public function test_register_proceeds_past_captcha_with_valid_token(): void
    {
        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'h-captcha-response' => 'valid-captcha-token',
        ])
            ->assertCreated()
            ->assertJsonMissingValidationErrors(['h-captcha-response']);
    }

    public function test_forgot_password_requires_captcha_token_when_enabled(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin@example.com',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['h-captcha-response']);
    }

    public function test_forgot_password_proceeds_past_captcha_with_valid_token(): void
    {
        Http::fake([
            'api.hcaptcha.com/siteverify' => Http::response(['success' => true], 200),
        ]);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin@example.com',
            'h-captcha-response' => 'valid-captcha-token',
        ])
            ->assertOk()
            ->assertJsonMissingValidationErrors(['h-captcha-response']);
    }

    public function test_two_factor_login_requires_captcha_token_when_enabled(): void
    {
        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => 'fake-token',
            'code' => '123456',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['h-captcha-response']);
    }
}
