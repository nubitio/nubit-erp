<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

class CashShiftClosureSummaryDto
{
    public function __construct(
        public int $paymentMethodId,
        public string $paymentMethod,
        public float $expectedAmount,
    ) {
    }
}
