<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

class CashShiftClosureSummaryDto
{
    public function __construct(
        public int $paymentMethodId,
        public string $paymentMethod,
        public string $expectedAmount,
    ) {
    }
}
