<?php

declare(strict_types=1);

namespace App\Billing\Recurring\Enum;

enum BillingCycle: string
{
    case MONTHLY = 'MONTHLY';
    case ANNUAL = 'ANNUAL';

    /**
     * Number of months per cycle, used to advance nextBillingDate.
     */
    public function months(): int
    {
        return match ($this) {
            self::MONTHLY => 1,
            self::ANNUAL => 12,
        };
    }
}
