<?php

declare(strict_types=1);

namespace App\Billing\Sale\Void;

use App\Identity\Activity\SalesActivityInterface;
use App\Sales\Entity\SaleRecord;

/** Leaves a line in the activity record when a sale is voided, so the owner can see who did it and why. */
readonly class SaleVoidActivity
{
    public function __construct(
        private SalesActivityInterface $activity,
    ) {}

    /** @param bool $confirmed false when SUNAT did not accept the void: nothing happened yet, so nothing is recorded */
    public function voided(SaleRecord $sale, string $reason, bool $confirmed = true): void
    {
        if (!$confirmed) {
            return;
        }

        $this->activity->saleVoided(
            $sale->getInvoiceFullNumber() ?? $sale->getId() ?? '',
            $sale->getGrandTotal(),
            $reason,
        );
    }
}
