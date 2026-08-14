<?php

declare(strict_types=1);

namespace App\Sales\Dto;

class SaleCollectPaymentDto
{
    public function __construct(
        public string $id,
        public string|float $amount,
        public int $paymentMethodId,
        public ?string $paidAt = null,
        public ?string $reference = null,
    ) {}
}
