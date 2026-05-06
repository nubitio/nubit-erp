<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Dto;

readonly class HydratedVoucherItem
{
    public function __construct(
        public string $productCode,
        public string $productUom,
        public float $quantity,
        public string $productName,
        public float $unitValue,
        public float $taxedAmount,
        public float $taxPercentage,
        public float $taxAmount,
        public string $taxType,
        public float $unitPrice,
        public float $discountAmount = 0.0,
        public ?float $discountPercent = null,
    ) {
    }
}
