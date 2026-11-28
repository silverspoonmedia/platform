<?php

namespace App\Support\CoreHub\Contracts;

use App\Support\CoreHub\Entitlement;
use App\Support\CoreHub\IdentitySnapshot;

/**
 * Kernel-owned write seam the hub uses to publish into a consumer.
 *
 * The consumer binds an implementation (upsert its local mirror, drop cached
 * figures, emit the change event); the hub's outbox calls it through this
 * interface so neither side imports the other. Both methods are idempotent by
 * `version`: replaying an older payload must leave the newer row untouched,
 * because an outbox retries until it succeeds.
 */
interface CoreHubReceiver
{
    /**
     * Apply a published identity snapshot.
     *
     * @return bool whether the row changed (false when the payload was stale)
     */
    public function applyIdentity(IdentitySnapshot $snapshot): bool;

    /**
     * Apply a published entitlement snapshot.
     *
     * @return bool whether the row changed (false when the payload was stale)
     */
    public function applyEntitlement(Entitlement $entitlement): bool;
}
