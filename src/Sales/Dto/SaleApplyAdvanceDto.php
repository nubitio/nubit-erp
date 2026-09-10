<?php

declare(strict_types=1);

namespace App\Sales\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Input for applying a customer's available advance to their open sales (#382):
 * FIFO oldest-first when {@see $applications} is empty, or over exactly the
 * sales listed there.
 */
class SaleApplyAdvanceDto
{
    /**
     * @param list<array{saleId: string, amount: string|float}> $applications
     */
    public function __construct(
        #[Assert\Positive]
        public int $customerId,
        public string|float $amount,
        public ?string $paidAt = null,
        #[Assert\All([new Assert\Collection(fields: [
            'saleId' => [new Assert\NotBlank(), new Assert\Type('string')],
            'amount' => [new Assert\NotBlank()],
        ])])]
        public array $applications = [],
    ) {}
}
