<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

use ApiPlatform\Metadata\ApiProperty;

final readonly class CashShiftXReportDto
{
    /**
     * @param array<array{paymentMethodId: int, paymentMethod: string, amount: string}> $salesByPaymentMethod
     */
    public function __construct(
        public string $openingBalance,
        public string $totalSales,
        public string $cashIn,
        public string $cashOut,
        public string $currentBalance,
        public int $shiftDurationMinutes,
        #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'object']])]
        public array $salesByPaymentMethod,
    ) {
    }
}
