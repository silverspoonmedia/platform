<?php

namespace App\Support\CoreHub\Tests\Concerns;

use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\Contracts\CoreHubClient;
use App\Support\CoreHub\IdentityRef;
use App\Support\CoreHub\IdentitySnapshot;

/**
 * Minimal client for a package test: no HTTP, fixed answers.
 *
 * Lives in the package's own test tree because a package test may not import a
 * host fixture (`DependencyDirectionTest`).
 */
class FakeCoreHubClient implements CoreHubClient
{
    public function __construct(
        public bool $configured = true,
        public ?IdentitySnapshot $identity = null,
        public ?Entitlement $entitlement = null,
    ) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function identity(IdentityRef $ref): ?IdentitySnapshot
    {
        return $this->identity;
    }

    public function entitlement(IdentityRef $ref): Entitlement
    {
        return $this->entitlement ?? Entitlement::none($ref->externalId, $ref->product);
    }

    public function gatewayCredentials(IdentityRef $ref): ?array
    {
        return null;
    }
}
