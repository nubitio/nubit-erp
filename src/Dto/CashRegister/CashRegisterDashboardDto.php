<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

use DateTimeImmutable;

class CashRegisterDashboardDto
{
    public function __construct(
        public int $id,
        public string $name,
        public ?int $cashShiftId,
        public ?DateTimeImmutable $openedAt,
        public ?DateTimeImmutable $closedAt,
        public ?string $cashier,
        public ?float $balance,
        public ?string $status,
        /** @var string Total sales (all payment methods) for the open shift today, as decimal string */
        public string $todaySales = '0.00',
    ) {
    }
}
