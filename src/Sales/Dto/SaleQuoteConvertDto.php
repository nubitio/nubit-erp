<?php

declare(strict_types=1);

namespace App\Sales\Dto;

class SaleQuoteConvertDto
{
    public function __construct(
        public string $id,
        public ?int $paymentMethodId = null,
        public ?string $warehouseId = null,
        public ?string $branchId = null,
    ) {}
}
