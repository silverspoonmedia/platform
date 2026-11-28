<?php

namespace App\Support\CoreHub\Tests\Unit;

use App\Support\CoreHub\Tests\Concerns\FakeCoreHubClient;
use App\Support\Testing\PackageTestCase;


use App\Support\CoreHub\Contracts\CoreHubClient;
use App\Support\CoreHub\CoreHubRegistry;
use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\Exceptions\HubUnreachable;
use App\Support\CoreHub\IdentityRef;
use App\Support\CoreHub\IdentitySnapshot;
use PHPUnit\Framework\Attributes\Test;

/**
 * The kernel declares a hub client, it never requires one.
 *
 * A consumer that talks to a core hub registers a class string; one that does not
 * leaves the registry empty and boots identically. That empty state is the contract
 * this test locks, plus the two shapes (snapshot and entitlement) round-tripping
 * through JSON the way a pushed payload arrives.
 */
class CoreHubClientContractTest extends PackageTestCase
{
    #[Test]
    public function an_empty_registry_answers_no_client(): void
    {
        $registry = new CoreHubRegistry;

        $this->assertFalse($registry->has());
        $this->assertNull($registry->clientClass());
        $this->assertNull($registry->client());
    }

    #[Test]
    public function registering_a_class_string_keeps_the_registry_octane_safe(): void
    {
        $registry = new CoreHubRegistry;
        $registry->register(FakeCoreHubClient::class);

        $this->assertTrue($registry->has());
        $this->assertSame(FakeCoreHubClient::class, $registry->clientClass());
        $this->assertInstanceOf(FakeCoreHubClient::class, $registry->client());
    }

    #[Test]
    public function a_snapshot_round_trips_through_the_pushed_payload_shape(): void
    {
        $original = new IdentitySnapshot(
            externalId: 'user-1',
            product: 'ledger',
            email: 'person@example.test',
            name: 'Person',
            roles: ['Admin'],
            permissions: ['view invoice'],
            version: 7,
            syncedAt: new \DateTimeImmutable('2026-11-28T03:00:00+00:00'),
        );

        $restored = IdentitySnapshot::fromArray(json_decode((string) json_encode($original->toArray()), true));

        $this->assertSame('user-1', $restored->externalId);
        $this->assertSame(['Admin'], $restored->roles);
        $this->assertSame(7, $restored->version);
        $this->assertFalse($restored->isDisabled());
        $this->assertSame('2026-11-28T03:00:00+00:00', $restored->syncedAt?->format(DATE_ATOM));
    }

    #[Test]
    public function an_entitlement_round_trips_and_answers_usability(): void
    {
        $paid = Entitlement::fromArray([
            'external_id' => 'user-1',
            'product' => 'ledger',
            'code' => 'ledger.pro',
            'tier' => 'paid',
            'status' => 'grace',
            'valid_from' => '2026-11-01T00:00:00+00:00',
            'valid_to' => '2026-12-01T00:00:00+00:00',
            'features' => ['exports'],
            'version' => 3,
        ]);

        $this->assertTrue($paid->isUsable());
        $this->assertTrue($paid->hasFeature('exports'));
        $this->assertFalse(Entitlement::none('user-1', 'ledger')->isUsable());
    }

    #[Test]
    public function unreachable_carries_a_non_secret_reason_and_a_translated_message(): void
    {
        $e = HubUnreachable::timeout();

        $this->assertSame('timeout', $e->reason);
        $this->assertSame(__('api/common.core_hub_unreachable'), $e->getMessage());
    }

    #[Test]
    public function an_identity_ref_needs_both_parts(): void
    {
        $this->assertTrue((new IdentityRef('user-1', 'ledger'))->isValid());
        $this->assertFalse((new IdentityRef('', 'ledger'))->isValid());
        $this->assertFalse((new IdentityRef('user-1', ''))->isValid());
    }
}
