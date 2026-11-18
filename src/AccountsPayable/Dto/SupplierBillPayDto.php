<?php

declare(strict_types=1);

namespace App\AccountsPayable\Dto;

class SupplierBillPayDto
{
    public function __construct(
        public string $id,
        public string|float $amount,
        public int $paymentMethodId,
        public ?string $paidAt = null,
        public ?string $reference = null,
        /** Treasury account the money leaves (bank or wallet); optional, the payment method's mapping is the default. */
        public ?string $moneyAccountId = null,
    ) {}
}
