<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use ApiPlatform\Metadata\ApiProperty;
use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * A shift and everything that happened in it, newest first: the opening, every sale (cash or not), the manual
 * movements and the withdrawals. The shift detail screen reads it in one call.
 */
final readonly class CashSessionLedgerDto
{
    /**
     * `kind` is one of `opening`, `sale`, `income`, `expense`, `withdrawal`; `amount` is signed (what leaves the
     * drawer is negative). `paymentMethod` and `document` are null where they do not apply.
     *
     * @param list<array{id: string, at: string, kind: string, document: ?string, paymentMethod: ?string, description: string, amount: string}> $entries
     */
    public function __construct(
        #[Groups(['cash_session_ledger:read'])]
        public int $id,
        #[Groups(['cash_session_ledger:read'])]
        public int $cashRegisterId,
        #[Groups(['cash_session_ledger:read'])]
        public string $cashRegisterName,
        #[Groups(['cash_session_ledger:read'])]
        public ?string $cashier,
        #[Groups(['cash_session_ledger:read'])]
        public DateTimeImmutable $openedAt,
        #[Groups(['cash_session_ledger:read'])]
        public ?DateTimeImmutable $closedAt,
        /** `open` or `closed`. */
        #[Groups(['cash_session_ledger:read'])]
        public string $status,
        #[Groups(['cash_session_ledger:read'])]
        public string $openingAmount,
        #[Groups(['cash_session_ledger:read'])]
        #[ApiProperty(openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'at' => ['type' => 'string'],
                    'kind' => ['type' => 'string'],
                    'document' => ['type' => 'string', 'nullable' => true],
                    'paymentMethod' => ['type' => 'string', 'nullable' => true],
                    'description' => ['type' => 'string'],
                    'amount' => ['type' => 'string'],
                ],
            ],
        ])]
        public array $entries,
    ) {}
}
