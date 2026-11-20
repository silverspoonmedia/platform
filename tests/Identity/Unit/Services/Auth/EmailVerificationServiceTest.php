<?php

namespace App\Domain\Identity\Tests\Unit\Services\Auth;

use App\Domain\Identity\Exceptions\Auth\EmailAlreadyVerifiedException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use App\Domain\Identity\Services\Auth\EmailVerificationService;
use App\Domain\Identity\Tests\TestCase;

class EmailVerificationServiceTest extends TestCase
{
    private EmailVerificationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EmailVerificationService(new UserRepository);
    }

    public function test_verify_marks_user_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verified = $this->service->verify(
            $user->id,
            sha1($user->getEmailForVerification()),
        );

        $this->assertNotNull($verified->email_verified_at);
    }

    public function test_send_notification_throws_when_already_verified(): void
    {
        $user = User::factory()->create();

        $this->expectException(EmailAlreadyVerifiedException::class);

        $this->service->sendNotification($user);
    }
}
