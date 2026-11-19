<?php

namespace App\Support\Billing\Contracts;

/**
 * Kernel-owned seam for tier bookkeeping that belongs to a product.
 *
 * A product analytics counter ("how many users are on paid/grace/free") is
 * product knowledge, but the transition happens inside the billing package's
 * entitlement mutation. The package derives the before/after tier from its own
 * generic probes and hands both to whichever observer registered for the
 * target — so no product import, and no product in the money path.
 */
interface TierObserver
{
    /**
     * `service.fulfillment_target` values this observer tracks.
     *
     * @return list<string>
     */
    public function targets(): array;

    /**
     * @param  'paid'|'grace'|'free'  $after
     */
    public function tierChanged(string $userId, ?string $before, string $after): void;
}
