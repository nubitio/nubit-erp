<?php

declare(strict_types=1);

namespace App\Payroll\Dto;

class PayrollRunPayDto
{
    public function __construct(
        public string $id,
        /** PaymentMethod id; CASH pays from caja, anything else from banco. */
        public ?int $paymentMethodId = null,
    ) {}
}
