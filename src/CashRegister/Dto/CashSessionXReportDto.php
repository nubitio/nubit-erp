<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CashSessionXReportDto
{
    /**
     * `totalSales` is what the shift sold (voided sales excluded): the sum of the payment methods plus what was
     * left on credit. The drawer's own balance is `currentBalance`.
     *
     * @param list<CashSessionPaymentMethodSalesDto> $salesByPaymentMethod
     */
    public function __construct(
        #[Groups(['cash_x_report:read'])]
        public string $openingBalance,
        #[Groups(['cash_x_report:read'])]
        public string $totalSales,
        #[Groups(['cash_x_report:read'])]
        public string $cashIn,
        #[Groups(['cash_x_report:read'])]
        public string $cashOut,
        #[Groups(['cash_x_report:read'])]
        public string $currentBalance,
        #[Groups(['cash_x_report:read'])]
        public int $shiftDurationMinutes,
        #[Groups(['cash_x_report:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'paymentMethodId' => ['type' => 'integer'],
                    'paymentMethod' => ['type' => 'string'],
                    'amount' => ['type' => 'string'],
                    'operations' => ['type' => 'integer'],
                ],
                'required' => ['paymentMethodId', 'paymentMethod', 'amount', 'operations'],
            ],
        ])]
        public array $salesByPaymentMethod,
        #[Groups(['cash_x_report:read'])]
        public ?string $cashSalesNet = null,
        #[Groups(['cash_x_report:read'])]
        public ?string $cashAdjustmentsOut = null,
        /** Sold in the shift and left on credit: not money yet, so it is in no payment method. */
        #[Groups(['cash_x_report:read'])]
        public string $receivableTotal = '0.00',
        #[Groups(['cash_x_report:read'])]
        public int $receivableOperations = 0,
    ) {}
}
