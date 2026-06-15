<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CashSessionPaymentMethodSalesDto
{
    public function __construct(
        #[Groups(['cash_x_report:read'])]
        public int $paymentMethodId,
        #[Groups(['cash_x_report:read'])]
        public string $paymentMethod,
        #[Groups(['cash_x_report:read'])]
        public string $amount,
    ) {
    }
}
