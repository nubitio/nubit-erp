<?php

namespace App\Dto\CashRegister;

class CashRegisterDashboardDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $cashShiftId,
        public ?\DateTimeImmutable $openedAt,
        public ?\DateTimeImmutable $closedAt,
        public ?string $cashier,
        public ?float $balance,
        public ?string $status,
    ) {
    }
}
