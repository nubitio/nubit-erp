<?php

declare(strict_types=1);

namespace App\CashRegister\Dto;

use DateTimeImmutable;
use Symfony\Component\Serializer\Attribute\Groups;

class CashRegisterDashboardDto
{
    public function __construct(
        #[Groups(['cash_register_dashboard:read'])]
        public int $id,
        #[Groups(['cash_register_dashboard:read'])]
        public string $name,
        #[Groups(['cash_register_dashboard:read'])]
        public ?int $cashSessionId,
        #[Groups(['cash_register_dashboard:read'])]
        public ?int $cashShiftId,
        #[Groups(['cash_register_dashboard:read'])]
        public ?DateTimeImmutable $openedAt,
        #[Groups(['cash_register_dashboard:read'])]
        public ?DateTimeImmutable $closedAt,
        #[Groups(['cash_register_dashboard:read'])]
        public ?string $cashier,
        #[Groups(['cash_register_dashboard:read'])]
        public ?float $balance,
        #[Groups(['cash_register_dashboard:read'])]
        public ?string $status,
        /** @var string Total sales (all payment methods) for the open shift today, as decimal string */
        #[Groups(['cash_register_dashboard:read'])]
        public string $todaySales = '0.00',
        /** When the register's latest closed shift closed; null if it never closed one. */
        #[Groups(['cash_register_dashboard:read'])]
        public ?DateTimeImmutable $lastClosedAt = null,
        /** Counted minus expected of that closing, as a decimal string; null if it was not counted. */
        #[Groups(['cash_register_dashboard:read'])]
        public ?string $lastDifference = null,
    ) {}
}
