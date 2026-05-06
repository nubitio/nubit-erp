<?php

declare(strict_types=1);

namespace App\Billing\Service;

final class InvoiceLineInput
{
    public function __construct(
        public string $quantity,
        public string $unitPrice,
        /** Per-line item discount percentage (0–100). Applied before the document-level global discount. */
        public string $discountPercent = '0',
    ) {
    }
}
