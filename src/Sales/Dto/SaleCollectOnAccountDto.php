<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for a "cobro a cuenta" (#381): one payment from a customer, spread over
 * their open sales — oldest first (FIFO) when {@see $applications} is empty, or
 * over exactly the sales listed there.
 */
class SaleCollectOnAccountDto
{
    /**
     * @param list<array{saleId: string, amount: string|float}> $applications
     */
    public function __construct(
        #[Assert\Positive]
        public int $customerId,
        public string|float $amount,
        #[Assert\Positive]
        public int $paymentMethodId,
        public ?string $paidAt = null,
        #[Assert\Length(max: 120)]
        public ?string $reference = null,
        #[Assert\All([new Assert\Collection(fields: [
            'saleId' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => [new Assert\NotBlank()],
        ])])]
        public array $applications = [],
        /** Treasury account the money lands in (bank or wallet); omitted = the payment method's mapped account. */
        public ?string $moneyAccountId = null,
    ) {}
}
