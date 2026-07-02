<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CashSessionClosureSummaryDto
{
    public function __construct(
        #[Groups(['cash_closure_summary:read'])]
        public int $paymentMethodId,
        #[Groups(['cash_closure_summary:read'])]
        public string $paymentMethod,
        #[Groups(['cash_closure_summary:read'])]
        public string $expectedAmount,
    ) {}
}
