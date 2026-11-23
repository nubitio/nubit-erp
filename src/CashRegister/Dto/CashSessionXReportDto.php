<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Attribute\Groups;

final readonly class CashSessionXReportDto
{
    /**
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
    ) {}
}
