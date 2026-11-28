<?php

namespace App\Support\CoreHub\Tests\Feature;

use App\Support\Testing\PackageTestCase;


use App\Support\CoreHub\Contracts\CoreHubReceiver;
use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\HubSignature;
use App\Support\CoreHub\IdentitySnapshot;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The machine surface stays off the browser surface, and stays closed without a
 * signature.
 *
 * A core hub pushes over `/internal/v1/*`: no SPA marker, no JWT cookie, no CSRF
 * nonce — a shared secret instead. Three things must hold, and each has burned a
 * project somewhere: the route is unreachable with a browser's credentials alone,
 * an unsigned request is a 401 (not a 500 from a missing binding), and a signed
 * request reaches the receiver the host bound.
 */
class CoreHubInternalRouteTest extends PackageTestCase
{
    private const SECRET = 'consumer-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['core_hub.inbound.consumers' => ['ledger' => self::SECRET]]);
    }

    /**
     * Testbench boots a skeleton, not `bootstrap/app.php`, so the machine surface
     * is mounted here exactly the way the host mounts it: its own file, its own
     * throttle, no browser middleware.
     */
    protected function defineRoutes($router): void
    {
        // `throttle:internal` is registered in the host's `bootstrap/app.php`;
        // Testbench has no rate limiter of that name, so the group mounts with
        // the route file's own middleware only.
        $path = app(\App\Support\Domain\DomainManager::class)->coreHubRoutePath();

        $router->group([], function ($router) use ($path): void {
            require $path;
        });
    }

    #[Test]
    public function the_internal_route_is_not_part_of_the_browser_api(): void
    {
        $this->assertFalse(
            $this->routeExists('internal.v1.identity.sync', 'api/v1'),
            'identity/sync must not be reachable under api/v1',
        );
        $this->assertTrue($this->routeExists('internal.v1.identity.sync'));
    }

    #[Test]
    public function an_unsigned_push_is_unauthorized(): void
    {
        $response = $this->postJson('/internal/v1/identity/sync', ['external_id' => 'user-1']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->assertSame(__('api/common.core_hub_signature_invalid'), $response->json('message'));
    }

    #[Test]
    public function a_signed_push_without_a_receiver_answers_501(): void
    {
        $body = (string) json_encode([
            'external_id' => 'user-1',
            'product' => 'ledger',
            'email' => 'person@example.test',
            'name' => 'Person',
            'version' => 1,
        ]);

        $response = $this->sendSigned('/internal/v1/identity/sync', $body);

        $response->assertStatus(Response::HTTP_NOT_IMPLEMENTED);
        $this->assertSame(__('api/common.core_hub_receiver_absent'), $response->json('message'));
    }

    #[Test]
    public function a_signed_push_reaches_the_receiver_the_host_bound(): void
    {
        $receiver = new RecordingCoreHubReceiver;
        $this->app->instance(CoreHubReceiver::class, $receiver);

        $body = (string) json_encode([
            'external_id' => 'user-1',
            'product' => 'ledger',
            'code' => 'ledger.pro',
            'tier' => 'paid',
            'status' => 'active',
            'features' => ['exports'],
            'version' => 4,
        ]);

        $this->sendSigned('/internal/v1/entitlement/change', $body)
            ->assertStatus(Response::HTTP_OK)
            ->assertJsonPath('data.accepted', true);

        $this->assertCount(1, $receiver->entitlements);
        $this->assertSame('ledger.pro', $receiver->entitlements[0]->code);
        $this->assertTrue($receiver->entitlements[0]->hasFeature('exports'));
    }

    #[Test]
    public function a_signature_from_a_different_body_is_refused(): void
    {
        $body = (string) json_encode(['external_id' => 'user-1', 'product' => 'ledger', 'version' => 1]);
        $this->app->instance(CoreHubReceiver::class, new RecordingCoreHubReceiver);

        $ts = time();
        $signature = HubSignature::sign(self::SECRET, $ts, 'POST', '/internal/v1/identity/sync', '{"tampered":true}');

        $this->postJson('/internal/v1/identity/sync', json_decode($body, true), [
            HubSignature::CONSUMER_HEADER => 'ledger',
            HubSignature::TIMESTAMP_HEADER => (string) $ts,
            HubSignature::HEADER => $signature,
        ])->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    private function sendSigned(string $path, string $body): \Illuminate\Testing\TestResponse
    {
        $ts = time();

        return $this->call(
            'POST',
            $path,
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_'.str_replace('-', '_', strtoupper(HubSignature::CONSUMER_HEADER)) => 'ledger',
                'HTTP_'.str_replace('-', '_', strtoupper(HubSignature::TIMESTAMP_HEADER)) => (string) $ts,
                'HTTP_'.str_replace('-', '_', strtoupper(HubSignature::HEADER)) => HubSignature::sign(self::SECRET, $ts, 'POST', $path, $body),
            ],
            $body,
        );
    }

    private function routeExists(string $name, ?string $prefix = null): bool
    {
        foreach (app('router')->getRoutes() as $route) {
            if ($route->getName() !== $name) {
                continue;
            }

            if ($prefix !== null && ! str_starts_with($route->uri(), $prefix.'/')) {
                continue;
            }

            return true;
        }

        return false;
    }
}

/**
 * Records what a push carried, so the assertion reads the parsed shape rather
 * than the raw JSON.
 */
class RecordingCoreHubReceiver implements CoreHubReceiver
{
    /** @var list<IdentitySnapshot> */
    public array $identities = [];

    /** @var list<Entitlement> */
    public array $entitlements = [];

    public function applyIdentity(IdentitySnapshot $snapshot): bool
    {
        $this->identities[] = $snapshot;

        return true;
    }

    public function applyEntitlement(Entitlement $entitlement): bool
    {
        $this->entitlements[] = $entitlement;

        return true;
    }
}
