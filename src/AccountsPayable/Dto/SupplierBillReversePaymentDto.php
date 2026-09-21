<?php

declare(strict_types=1);

namespace App\AccountsPayable\Dto;

class SupplierBillReversePaymentDto
{
    public function __construct(
        public string $id,
        public int $paymentId,
    ) {}
}
