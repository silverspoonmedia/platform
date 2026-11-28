<?php

namespace App\Support\CoreHub\Contracts;

use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\IdentityRef;
use App\Support\CoreHub\IdentitySnapshot;

/**
 * Kernel-owned read seam toward a core hub.
 *
 * The hub owns identity and the paid layer; a consumer owns its own user table,
 * JWT and session. This interface is the only thing a consumer may call to learn
 * what the hub knows — the kernel declares it, the host binds an implementation
 * (HTTP to the hub, or a local reading of the pushed mirror), and every caller
 * sits behind `app()->bound()`.
 *
 * Two invariants hold for every implementation:
 *
 * 1. **No cross-service join.** The returned shapes are snapshots; nothing here
 *    answers with an Eloquent model of another database.
 * 2. **Fail soft.** When the hub cannot be reached the implementation throws
 *    {@see \App\Support\CoreHub\Exceptions\HubUnreachable}; the caller falls back
 *    to its local mirror and marks the answer stale. A hub outage degrades a
 *    consumer, it never takes it down.
 */
interface CoreHubClient
{
    /**
     * Whether a hub is wired at all. A host without one boots identically.
     */
    public function isConfigured(): bool;

    /**
     * The hub's canonical record for one identity, or `null` when the hub has
     * never heard of it.
     */
    public function identity(IdentityRef $ref): ?IdentitySnapshot;

    /**
     * The paid layer for one product, or {@see Entitlement::none()} when the
     * hub publishes none — absence is not an error.
     */
    public function entitlement(IdentityRef $ref): Entitlement;

    /**
     * Gateway credential slot the hub assigns to this consumer, so a consumer
     * never stores its own provider secret.
     *
     * @return array<string, mixed>|null
     */
    public function gatewayCredentials(IdentityRef $ref): ?array;
}
