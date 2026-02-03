<?php

declare(strict_types=1);

namespace App\Service;

final class InvoiceLineAmounts
{
    public function __construct(
        public string $quantity,
        public string $unitPrice,
        public string $unitValue,
        public string $baseAmount,
        public string $discountAmount,
        public string $taxableAmount,
        public string $chargesAmount,
        public string $taxAmount,
        public string $totalAmount,
    ) {
    }
}
