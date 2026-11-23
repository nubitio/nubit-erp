<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\CashRegister\State\CashShiftSalesProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * The sales rung up in the shift the signed-in user has open, newest first, so the cashier can trace a
 * cash difference to the sale that caused it. It is deliberately not a view of the branch's sales: the
 * user only ever sees their own shift, whatever else they are allowed to read.
 */
#[ApiResource(
    shortName: 'CashShiftSales',
    operations: [
        new Get(
            uriTemplate: '/cash-pos-state/sales',
            provider: CashShiftSalesProvider::class,
            security: "is_granted('perm:cash.view')",
        ),
    ],
    normalizationContext: ['groups' => ['cash_shift_sales:read']],
)]
final readonly class CashShiftSalesDto
{
    /**
     * @param int|null $sessionId the open session the sales belong to; null when the user has none
     * @param int $count sales that count towards the shift (voided ones are listed but not counted)
     * @param string $total sum of those sales
     * @param list<array{id: string, issuedAt: string|null, status: string, total: string, documentType: string|null, documentNumber: string|null, sunatStatus: string|null, payments: list<array{method: string, amount: string}>}> $sales
     */
    public function __construct(
        #[Groups(['cash_shift_sales:read'])]
        #[ApiProperty(openapiContext: ['type' => 'integer', 'nullable' => true])]
        public ?int $sessionId,
        #[Groups(['cash_shift_sales:read'])]
        public int $count,
        #[Groups(['cash_shift_sales:read'])]
        public string $total,
        #[Groups(['cash_shift_sales:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'issuedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'status' => ['type' => 'string'],
                    'total' => ['type' => 'string'],
                    'documentType' => ['type' => 'string', 'nullable' => true],
                    'documentNumber' => ['type' => 'string', 'nullable' => true],
                    'sunatStatus' => ['type' => 'string', 'nullable' => true],
                    'payments' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => ['method' => ['type' => 'string'], 'amount' => ['type' => 'string']],
                            'required' => ['method', 'amount'],
                        ],
                    ],
                ],
                'required' => ['id', 'status', 'total', 'payments'],
            ],
        ])]
        public array $sales,
    ) {}
}
