<?php

namespace App\Support\Billing;

/**
 * Convenience accessor for the kernel fulfillment registry.
 *
 * Product providers run before the billing package in a normal boot, but a
 * package provider can also run first — resolving through the container once
 * removes that ordering question from every call site.
 */
final class Fulfillment
{
    public static function registry(): FulfillmentRegistry
    {
        if (! app()->bound(FulfillmentRegistry::class)) {
            app()->singleton(FulfillmentRegistry::class);
        }

        return app(FulfillmentRegistry::class);
    }

    public static function handler(?string $target): ?Contracts\FulfillmentHandler
    {
        return self::registry()->forTarget($target);
    }

    /**
     * Drop memoized state in every registered product after an entitlement mutation.
     */
    public static function entitlementsMutated(string $userId): void
    {
        foreach (self::registry()->all() as $handler) {
            $handler->onEntitlementsMutated($userId);
        }
    }
}
