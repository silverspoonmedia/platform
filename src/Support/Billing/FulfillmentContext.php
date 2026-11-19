<?php

namespace App\Support\Billing;

use Carbon\CarbonInterface;

/**
 * Kernel-owned, data-only snapshot of one entitlement mutation.
 *
 * The billing package owns the catalog and the ledger; a product domain owns
 * what fulfillment *means* for its own target. Handing a product a shape like
 * this keeps the package from importing a product service — and keeps a host
 * without that product booting normally, because no handler is registered.
 *
 * Construction needs the ledger models, which belong to `silverspoonmedia/billing`,
 * so the builder lives there (`Service\Support\ServiceEntitlementSnapshot`): the
 * kernel declares the shape, the owning package fills it.
 */
final class FulfillmentContext
{
    /** A purchase just paid and the entitlement is now Active. */
    public const PHASE_ACTIVATED = 'activated';

    /** A stack renewal extended every active/grace addon. */
    public const PHASE_EXTENDED = 'extended';

    /** An entitlement passed its grace window and expired. */
    public const PHASE_EXPIRED = 'expired';

    /**
     * @param  list<string>  $activeCodes  Service codes currently Active/Grace for the user's target.
     */
    public function __construct(
        public readonly string $userId,
        public readonly ?string $purchaseId,
        public readonly ?string $entitlementId,
        public readonly ?string $serviceId,
        public readonly string $serviceCode,
        public readonly string $fulfillmentTarget,
        public readonly ?string $entitlementCode,
        public readonly int $quantity,
        public readonly int $storageBytes,
        public readonly ?CarbonInterface $periodStart,
        public readonly ?CarbonInterface $periodEnd,
        public readonly string $phase,
        public readonly array $activeCodes = [],
    ) {}

    public function contributesStorage(): bool
    {
        return $this->storageBytes > 0;
    }
}
