<?php

namespace App\Domain\Identity\Tests\Unit\Services\Auth;

use App\Domain\Identity\Exceptions\Auth\CurrentPasswordIncorrectException;
use App\Domain\Identity\Exceptions\Auth\InvalidPasswordResetTokenException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use App\Domain\Identity\Services\Auth\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetServiceTest extends TestCase
{
    use RefreshDatabase;

    private PasswordResetService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new PasswordResetService(new UserRepository);
    }

    public function test_reset_updates_password_with_valid_token(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->service->reset([
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $user->refresh();

        $this->assertTrue(Hash::check('new-password-123', $user->password));
    }

    public function test_reset_throws_for_invalid_token(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidPasswordResetTokenException::class);

        $this->service->reset([
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);
    }

    public function test_change_password_throws_when_current_password_is_wrong(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $this->expectException(CurrentPasswordIncorrectException::class);

        $this->service->changePassword($user->id, [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
        ]);
    }
}
