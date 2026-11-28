<?php

namespace App\Support\CoreHub\Tests\Feature;

use App\Support\CoreHub\Contracts\CoreHubClient;
use App\Support\CoreHub\CoreHubRegistry;
use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\HubSignature;
use App\Support\CoreHub\IdentityRef;
use App\Support\CoreHub\IdentitySnapshot;
use App\Support\Testing\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * The read half of the machine surface: a signed pull a consumer makes.
 *
 * The write half (`identity/sync`, `entitlement/change`) is the outbox push,
 * proved in {@see CoreHubInternalRouteTest}. A consumer that only ever receives
 * cannot answer after a restart or a missed push, so the same origin serves a
 * signed read — and a read is the one call that must stay cheap on a page load.
 *
 * What this file pins is the contract around that read, not the hub's controller:
 * an unsigned read is a 401 with the shared sentence, a registry with no hub
 * binds no client, and the client contract a host binds is exactly the four
 * methods the kernel declares.
 */
class CoreHubIdentitySnapshotRouteTest extends PackageTestCase
{
    private const SECRET = 'consumer-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config(['core_hub.inbound.consumers' => ['ledger' => self::SECRET]]);
    }

    protected function defineRoutes($router): void
    {
        $path = dirname(__DIR__, 3).'/routes/core-hub.php';

        $router->group([], function ($router) use ($path): void {
            require $path;
        });
    }

    #[Test]
    public function an_unsigned_push_is_unauthorized_with_the_shared_sentence(): void
    {
        // The kernel mounts the write half of the machine surface; the read half
        // is the hub's own (its origin, its data), so this file pins the kernel's.
        $response = $this->postJson('/internal/v1/identity/sync', ['external_id' => 'user-1']);

        $response->assertStatus(Response::HTTP_UNAUTHORIZED);
        $this->assertSame(__('api/common.core_hub_signature_invalid'), $response->json('message'));
    }

    #[Test]
    public function a_stale_epoch_is_refused_even_when_signed(): void
    {
        $ts = time() - (HubSignature::TOLERANCE_SECONDS + 60);
        $path = '/internal/v1/identity/sync';
        $body = (string) json_encode(['external_id' => 'user-1', 'product' => 'ledger', 'version' => 1]);

        $this->call('POST', $path, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            HubSignature::CONSUMER_HEADER => 'ledger',
            HubSignature::TIMESTAMP_HEADER => (string) $ts,
            HubSignature::HEADER => HubSignature::sign(self::SECRET, $ts, 'POST', $path, $body),
        ], $body)->assertStatus(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function the_registry_answers_null_before_a_consumer_registers(): void
    {
        // The kernel binds the registry, never a client: a host with no hub boots
        // identically and every bound-guarded caller answers "no hub".
        $this->assertFalse(app(CoreHubRegistry::class)->has());
        $this->assertNull(app(CoreHubRegistry::class)->client());
    }

    #[Test]
    public function a_registered_client_resolves_through_the_contract(): void
    {
        $client = new class implements CoreHubClient
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function identity(IdentityRef $ref): ?IdentitySnapshot
            {
                return new IdentitySnapshot(
                    externalId: $ref->externalId,
                    product: $ref->product,
                    email: 'person@example.test',
                    name: 'Person',
                    roles: ['User'],
                    permissions: [],
                    version: 7,
                    syncedAt: now(),
                );
            }

            public function entitlement(IdentityRef $ref): Entitlement
            {
                return Entitlement::none($ref->externalId, $ref->product);
            }

            public function gatewayCredentials(IdentityRef $ref): ?array
            {
                return null;
            }
        };

        $class = new \ReflectionClass($client)->getName();

        $this->app->instance(CoreHubClient::class, $client);
        $this->app->instance($class, $client);
        app(CoreHubRegistry::class)->register($class);

        $this->assertTrue(app(CoreHubRegistry::class)->has());
        $this->assertSame($client, app(CoreHubRegistry::class)->client());

        $snapshot = app(CoreHubRegistry::class)->client()?->identity(new IdentityRef('user-1', 'ledger'));

        $this->assertNotNull($snapshot);
        $this->assertSame(7, $snapshot->version);
    }
}
