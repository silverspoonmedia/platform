<?php

namespace App\Domain\Identity\Tests\Feature\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Notifications\ResetPasswordNotification;
use App\Domain\Identity\Services\Auth\TwoFactorService;
use App\Support\Auth\SpaAuthCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\SeedsRolesAndPermissions;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRolesAndPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_protected_auth_routes_require_bearer_token(): void
    {
        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.bearer_required'),
            ]);

        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.bearer_required'),
            ]);
    }

    public function test_protected_auth_routes_reject_invalid_bearer_token_without_redirect(): void
    {
        $headers = ['Authorization' => 'Bearer invalid-token'];

        $this->get('/api/v1/auth/me', $headers)
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.token_invalid'),
            ]);

        $this->post('/api/v1/auth/logout', [], $headers)
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.token_invalid'),
            ]);
    }

    public function test_register_login_me_and_logout_flow(): void
    {
        $register = $this->postJson('/api/v1/auth/register', [
            'name' => 'Auth User',
            'email' => 'auth@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonStructure([
                'status',
                'message',
                'data' => ['user', 'token_type', 'expires_in'],
            ])
            ->assertJsonMissingPath('data.token');

        // Cookie-mode keeps the JWT HttpOnly-only (stripped from the body). Mint a
        // Bearer token directly for the follow-up calls.
        $token = auth('api')->login(User::query()->where('email', 'auth@example.com')->firstOrFail());

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'auth@example.com')
            ->assertJsonPath('data.roles.0', 'User');

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();
    }

    public function test_login_with_remember_me_returns_extended_expiry(): void
    {
        config(['jwt.remember_ttl' => 90]);

        User::factory()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'remember@example.com',
            'password' => 'password123',
            'remember_me' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.remember', true)
            ->assertJsonPath('data.expires_in', 90 * 60);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'auth@example.com',
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'auth@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.invalid_credentials'),
            ]);
    }

    public function test_login_sets_spa_auth_cookie(): void
    {
        User::factory()->create([
            'email' => 'spa-cookie@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'spa-cookie@example.com',
            'password' => 'password123',
        ])->assertOk();

        $response->assertCookie(SpaAuthCookie::NAME);
    }

    public function test_logout_clears_spa_auth_cookie(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = auth('api')->login($user);

        $response = $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $response->assertCookieExpired(SpaAuthCookie::NAME);
    }

    public function test_logout_blacklists_the_bearer_token_for_follow_up_requests(): void
    {
        config(['jwt.blacklist_grace_period' => 0]);

        $user = User::factory()->create();
        $user->assignRole('User');
        $token = auth('api')->login($user);

        $this->postJson('/api/v1/auth/logout', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        auth()->forgetGuards();

        $this->getJson('/api/v1/auth/me', [
            'Authorization' => 'Bearer '.$token,
        ])->assertUnauthorized();
    }

    public function test_logout_cookie_cannot_authenticate_a_follow_up_request(): void
    {
        config(['jwt.blacklist_grace_period' => 0]);

        $user = User::factory()->create();
        $user->assignRole('User');
        $token = auth('api')->login($user);

        $this->withCookie(SpaAuthCookie::NAME, $token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertCookieExpired(SpaAuthCookie::NAME);

        $this->withCookie(SpaAuthCookie::NAME, $token)
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_forgot_and_reset_password_flow(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertOk();

        Notification::assertSentTo($user, ResetPasswordNotification::class);

        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_email_verification_and_resend_flow(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('User');

        $verificationUrl = URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addHour(),
            [
                'id' => $user->id,
                'hash' => sha1($user->getEmailForVerification()),
            ],
        );

        $this->getJson($verificationUrl)
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/auth.email_verified'),
            ]);

        $user->refresh();

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()->assertJsonMissingPath('data.token');

        // Body token is stripped in cookie mode; mint a Bearer token directly.
        auth()->forgetGuards();
        $token = auth('api')->login($user);
        auth()->forgetGuards();

        $this->postJson('/api/v1/auth/email/verification-notification', [], [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertStatus(422)
            ->assertJson([
                'status' => 'error',
                'message' => __('api/auth.email_already_verified'),
            ]);
    }

    public function test_admin_route_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $token = auth('api')->login($user);

        $this->getJson('/api/v1/admin/ping', [
            'Authorization' => 'Bearer '.$token,
        ])
            ->assertForbidden()
            ->assertJson([
                'status' => 'error',
                'message' => __('api/common.unauthorized'),
            ]);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $adminToken = auth('api')->login($admin);

        $this->getJson('/api/v1/admin/ping', [
            'Authorization' => 'Bearer '.$adminToken,
        ])
            ->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => __('api/admin.access_granted'),
            ]);
    }

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $user->assignRole('User');

        $token = auth('api')->login($user);

        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_authenticated_user_can_enable_and_disable_two_factor_authentication(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');
        $token = auth('api')->login($user);

        $setup = $this->postJson('/api/v1/auth/two-factor/setup', [], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['secret', 'qr_code_url', 'qr_code_svg']]);

        $secret = $setup->json('data.secret');
        $code = app('pragmarx.google2fa')->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => $code], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('message', __('api/auth.two_factor_enabled'));

        $user->refresh();
        $this->assertNotNull($user->two_factor_confirmed_at);

        $code = app('pragmarx.google2fa')->getCurrentOtp($secret);

        $this->postJson('/api/v1/auth/two-factor/disable', ['code' => $code], [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('message', __('api/auth.two_factor_disabled'));

        $user->refresh();
        $this->assertNull($user->two_factor_secret);
    }

    public function test_login_requires_second_factor_when_two_factor_is_enabled(): void
    {
        $user = User::factory()->create([
            'email' => 'two-factor@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $service = new TwoFactorService;
        $setup = $service->startSetup($user);
        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $service->confirmSetup($user, $code);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'two-factor@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('data.two_factor_required', true)
            ->assertJsonMissingPath('data.token');

        $loginCode = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);

        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $challenge->json('data.two_factor_token'),
            'code' => $loginCode,
        ])->assertOk()
            ->assertJsonStructure(['data' => ['token_type', 'expires_in']])
            ->assertJsonMissingPath('data.token')
            ->assertCookie(SpaAuthCookie::NAME);
    }

    public function test_two_factor_login_rejects_invalid_code(): void
    {
        $user = User::factory()->create([
            'email' => 'bad-two-factor@example.com',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('User');

        $service = new TwoFactorService;
        $setup = $service->startSetup($user);
        $code = app('pragmarx.google2fa')->getCurrentOtp($setup['secret']);
        $service->confirmSetup($user, $code);

        $challenge = $this->postJson('/api/v1/auth/login', [
            'email' => 'bad-two-factor@example.com',
            'password' => 'password123',
        ])->assertOk();

        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $challenge->json('data.two_factor_token'),
            'code' => '000000',
        ])->assertUnauthorized()
            ->assertJsonPath('message', __('api/auth.invalid_two_factor_code'));
    }
}
