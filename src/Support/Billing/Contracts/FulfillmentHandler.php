<?php

namespace App\Support\Billing\Contracts;

use App\Support\Billing\FulfillmentContext;

/**
 * Kernel-owned seam between the billing package and a product domain.
 *
 * `silverspoonmedia/billing` activates/extends/expires an entitlement and then asks
 * this registry what the *product* should do about it: recompute a quota,
 * credit an AI balance, guard a one-time free registration. The package never
 * imports `App\Domain\Archivd\*` or `App\Domain\Microstock\*`; the product
 * registers a handler in its own provider, behind `app()->bound()`.
 *
 * A concept with no handler is a no-op, which is exactly what a host that did
 * not install the product should get.
 */
interface FulfillmentHandler
{
    /** Fixed-length period this target bills in, or null for the catalog's calendar interval. */
    public const PERIOD_DAYS = 30;

    /**
     * `service.fulfillment_target` values this handler answers to.
     *
     * @return list<string>
     */
    public function targets(): array;

    /**
     * Catalog codes that must never count as a paid entitlement.
     *
     * A product retires a SKU by code (the free tier became config-only), and
     * the package cannot know that list without importing the product — so the
     * product declares it and every paid probe filters on it.
     *
     * @return list<string>
     */
    public function freeCodes(): array;

    /**
     * This target bills a fixed number of days, or null to keep the calendar
     * interval (month/quarter/year) from the catalog row.
     *
     * Fixed days exist so entitlement length and the unused-gap credit math use
     * one period definition instead of drifting apart.
     */
    public function fixedPeriodDays(): int;

    /**
     * Reject a checkout before money moves.
     *
     * Throwing is the contract: the package lets the exception escape to the
     * controller, so a product keeps owning its own message and status code.
     */
    public function guardCheckout(FulfillmentContext $context): void;

    /**
     * A purchase just paid and the entitlement is now Active.
     */
    public function onActivated(FulfillmentContext $context): void;

    /**
     * A stack renewal extended every active/grace addon.
     */
    public function onExtended(FulfillmentContext $context): void;

    /**
     * An entitlement passed its grace window and expired.
     *
     * The returned array is logged verbatim by the caller (quota + purge
     * counters). Returning `[]` means "nothing to report".
     *
     * @return array<string, mixed>
     */
    public function onExpired(FulfillmentContext $context): array;

    /**
     * Any entitlement row for this user mutated — drop memoized product state.
     *
     * Called after every activation, extension and expiry, including ones the
     * handler does not otherwise care about, because a cached quota probe can
     * outlive the row it was built from.
     */
    public function onEntitlementsMutated(string $userId): void;
}
