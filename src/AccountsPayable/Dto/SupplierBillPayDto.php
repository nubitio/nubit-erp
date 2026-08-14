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
    ) {}
}
