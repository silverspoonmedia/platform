<?php

namespace App\Support\Billing\Contracts;

use Carbon\CarbonInterface;

/**
 * Kernel-owned subscription interval contract.
 *
 * `silverspoonmedia/billing` owns the catalog and its `BillingInterval` enum;
 * the kernel clock only needs "how long is one period of this interval", so the
 * contract is declared here instead of importing the package enum.
 *
 * The package enum implements it, which keeps `BillingClock` free of any
 * `App\Domain\*` import while the call site still passes its own enum.
 */
interface SubscriptionInterval
{
    /**
     * The moment one interval after `$from`, in the interval's own calendar.
     */
    public function addPeriod(CarbonInterface $from): CarbonInterface;
}
