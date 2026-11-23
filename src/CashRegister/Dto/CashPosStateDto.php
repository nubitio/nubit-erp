<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\CashRegister\State\CashPosStateProvider;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * What the point of sale needs to decide whether the signed-in user can sell: the cash session they
 * have open, if any, and the registers of the current branch they could open one on.
 */
#[ApiResource(
    shortName: 'CashPosState',
    operations: [
        // Its own resource and a path that is not `/cash-sessions/{id}`: declared inside CashSession it became
        // that entity's item route and every `cashSession` IRI came out as `/api/cash-sessions/current`.
        new Get(
            uriTemplate: '/cash-pos-state',
            provider: CashPosStateProvider::class,
            security: "is_granted('perm:cash.view')",
        ),
    ],
    normalizationContext: ['groups' => ['cash_pos_state:read']],
)]
final readonly class CashPosStateDto
{
    /**
     * @param array{id: int, cashRegisterId: int, cashRegisterName: string, openedAt: string, openingAmount: string}|null $session the user's open session
     * @param list<array{id: int, name: string}> $availableRegisters registers of the branch with no open session
     * @param list<array{id: int, name: string, cashier: string|null, openedAt: string}> $busyRegisters registers of the branch somebody else has open, so the cashier knows whom to wait for
     */
    public function __construct(
        #[Groups(['cash_pos_state:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'object',
            'nullable' => true,
            'properties' => [
                'id' => ['type' => 'integer'],
                'cashRegisterId' => ['type' => 'integer'],
                'cashRegisterName' => ['type' => 'string'],
                'openedAt' => ['type' => 'string', 'format' => 'date-time'],
                'openingAmount' => ['type' => 'string'],
            ],
        ])]
        public ?array $session,
        #[Groups(['cash_pos_state:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['id' => ['type' => 'integer'], 'name' => ['type' => 'string']],
                'required' => ['id', 'name'],
            ],
        ])]
        public array $availableRegisters,
        #[Groups(['cash_pos_state:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'cashier' => ['type' => 'string', 'nullable' => true],
                    'openedAt' => ['type' => 'string', 'format' => 'date-time'],
                ],
                'required' => ['id', 'name', 'openedAt'],
            ],
        ])]
        public array $busyRegisters = [],
    ) {}
}
