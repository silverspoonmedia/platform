<?php

namespace App\Domain\Identity\Tests\Unit\Http\Controllers\API\V1\Auth;

use App\Domain\Identity\Http\Controllers\API\V1\Auth\AuthController;
use App\Domain\Identity\Http\Requests\Auth\LoginRequest;
use App\Domain\Identity\Http\Requests\Auth\RegisterRequest;
use App\Domain\Identity\Services\Auth\AuthService;
use App\Domain\Identity\Tests\TestCase;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Mockery;
use Mockery\MockInterface;

class AuthControllerTest extends TestCase
{
    private AuthService&MockInterface $authService;

    private AuthController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authService = Mockery::mock(AuthService::class);
        $this->controller = new AuthController($this->authService);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    private function toTestResponse(JsonResponse $response): TestResponse
    {
        return TestResponse::fromBaseResponse($response);
    }

    public function test_register_delegates_to_service(): void
    {
        $payload = ['user' => ['email' => 'bob@example.com'], 'token' => 'jwt'];

        $request = Mockery::mock(RegisterRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $this->authService
            ->shouldReceive('register')
            ->once()
            ->andReturn($payload);

        $response = $this->toTestResponse($this->controller->register($request));

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Registration successful.',
                'data' => $payload,
            ]);
    }

    public function test_login_delegates_to_service(): void
    {
        $payload = ['user' => ['email' => 'bob@example.com'], 'token' => 'jwt'];

        $request = Mockery::mock(LoginRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $this->authService
            ->shouldReceive('login')
            ->once()
            ->andReturn($payload);

        $response = $this->toTestResponse($this->controller->login($request));

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Login successful.',
            ]);
    }
}
