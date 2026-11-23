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
     * @param list<array{id: string, issuedAt: string|null, status: string, total: string, receivable: string, documentType: string|null, documentNumber: string|null, sunatStatus: string|null, payments: list<array{method: string, amount: string}>}> $sales
     * @param list<array{id: int, name: string, amount: string, operations: int}> $byMethod what the shift sold, by the way it was paid
     * @param array{amount: string, operations: int} $receivable what was left on credit: not money yet
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
                    'receivable' => ['type' => 'string'],
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
                'required' => ['id', 'status', 'total', 'receivable', 'payments'],
            ],
        ])]
        public array $sales,
        #[Groups(['cash_shift_sales:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'amount' => ['type' => 'string'],
                    'operations' => ['type' => 'integer'],
                ],
                'required' => ['id', 'name', 'amount', 'operations'],
            ],
        ])]
        public array $byMethod,
        #[Groups(['cash_shift_sales:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'object',
            'properties' => ['amount' => ['type' => 'string'], 'operations' => ['type' => 'integer']],
            'required' => ['amount', 'operations'],
        ])]
        public array $receivable,
    ) {}
}
