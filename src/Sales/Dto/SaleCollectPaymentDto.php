<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class SaleCollectPaymentDto
{
    public function __construct(
        public string $id,
        public string|float $amount,
        public int $paymentMethodId,
        public ?string $paidAt = null,
        #[Assert\Length(max: 120)]
        public ?string $reference = null,
        /** Treasury account the money lands in (bank or wallet); omitted = the payment method's mapped account. */
        public ?string $moneyAccountId = null,
    ) {}
}
