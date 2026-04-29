<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class CashRegisterStatusDto
{
    public function __construct(
        public int $cashRegisterId,
        public string $cashRegisterName,
        public string $status,
        public ?int $shiftId,
        public ?string $openedAt,
        public ?string $openedBy,
        public ?string $closedAt,
        public ?float $openingBalance,
        public ?float $expectedAmount,
        public ?float $countedAmount,
        public ?float $difference,
    ) {
    }
}
