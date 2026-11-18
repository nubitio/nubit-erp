<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for receiving a customer advance (#382): money in from a customer with
 * no target sale.
 */
class SaleReceiveAdvanceDto
{
    public function __construct(
        #[Assert\Positive]
        public int $customerId,
        public string|float $amount,
        #[Assert\Positive]
        public int $paymentMethodId,
        public ?string $paidAt = null,
        #[Assert\Length(max: 120)]
        public ?string $reference = null,
        /** Treasury account the money lands in (bank or wallet); omitted = the payment method's mapped account. */
        public ?string $moneyAccountId = null,
    ) {}
}
