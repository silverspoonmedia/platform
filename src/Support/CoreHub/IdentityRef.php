<?php

namespace App\Support\CoreHub;

/**
 * Kernel-owned handle for one person, keyed by the hub's identity.
 *
 * A consumer keeps its own `users` row (its own JWT, its own session) and the
 * hub keeps the canonical record. Nothing is ever joined across the two
 * databases, so the only value they share is this reference — the hub's UUID
 * stored on the consumer row as `users.external_id`.
 *
 * `product` scopes the reference: the same person may hold a ledger row in one
 * product and none in another, and an entitlement is always asked for one
 * product at a time.
 */
final readonly class IdentityRef
{
    public function __construct(
        public string $externalId,
        public string $product,
    ) {}

    public function isValid(): bool
    {
        return $this->externalId !== '' && $this->product !== '';
    }
}
