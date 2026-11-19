<?php

namespace App\Support\Billing;

use App\Support\Billing\Contracts\FulfillmentHandler;
use App\Support\Billing\Contracts\SubscriptionInterval;
use Carbon\CarbonInterface;

/**
 * One place that answers "where does this period end".
 *
 * The catalog row carries a calendar interval, but a target that bills fixed
 * days (storage plans) must use exactly the same period the unused-gap credit
 * math uses, or the two drift. The product handler declares its fixed length;
 * everything else keeps the calendar interval.
 */
final class BillingClock
{
    public static function addPeriod(?string $target, CarbonInterface $from, SubscriptionInterval $interval): CarbonInterface
    {
        $days = self::fixedDays($target);

        if ($days !== null) {
            return $from->copy()->addDays($days);
        }

        return $interval->addPeriod($from);
    }

    public static function fixedDays(?string $target): ?int
    {
        $handler = Fulfillment::handler($target);

        if ($handler === null) {
            return null;
        }

        $days = $handler->fixedPeriodDays();

        return $days !== null && $days > 0 ? $days : null;
    }

    /**
     * Credit math period in days: the handler's fixed length, else the calendar
     * interval's nominal month.
     */
    public static function creditDays(?string $target): int
    {
        return self::fixedDays($target) ?? FulfillmentHandler::PERIOD_DAYS;
    }
}
