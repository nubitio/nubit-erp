<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final class CashRegisterStatusDto
{
    public function __construct(
        public readonly int $cashRegisterId,
        public readonly string $cashRegisterName,
        public readonly string $status,
        public readonly ?int $cashShiftId,
        public readonly ?string $openedAt,
        public readonly ?string $openedBy,
        public readonly ?string $closedAt,
        public readonly ?float $openingBalance,
        public readonly ?float $expectedAmount,
        public readonly ?float $countedAmount,
        public readonly ?float $difference,
    ) {
    }
}
