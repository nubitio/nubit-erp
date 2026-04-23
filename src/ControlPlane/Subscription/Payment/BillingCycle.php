<?php

declare(strict_types=1);

namespace App\ControlPlane\Subscription\Payment;

enum BillingCycle: string
{
    case Monthly = 'monthly';
    case Annual = 'annual';

    /**
     * Number of months per cycle.
     */
    public function months(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Annual => 12,
        };
    }

    /**
     * Annual discount: pay 10 months, get 12.
     * Returns the multiplier (number of months to charge).
     */
    public function chargeableMonths(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Annual => 10,
        };
    }
}
