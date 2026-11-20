<?php

namespace App\Domain\Identity\Tests\Unit\Repositories\Eloquent;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Repositories\Eloquent\UserRepository;
use App\Domain\Identity\Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new UserRepository;
    }

    public function test_create_and_find_by_email(): void
    {
        $user = $this->repository->create([
            'name' => 'Repo User',
            'email' => 'repo@example.com',
            'password' => 'password123',
        ]);

        $this->assertInstanceOf(User::class, $user);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $user->id,
        );

        $found = $this->repository->findByEmail('repo@example.com');

        $this->assertNotNull($found);
        $this->assertSame($user->id, $found->id);
    }

    public function test_mark_email_as_verified(): void
    {
        $user = User::factory()->unverified()->create();

        $verified = $this->repository->markEmailAsVerified($user->id);

        $this->assertNotNull($verified->email_verified_at);
    }
}
