<?php

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
