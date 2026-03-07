<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

use DateTimeImmutable;

class CashRegisterOpenResponseDto
{
    public function __construct(
        /** @var int The cash_sessions.id for the newly opened session (used for all v2 routing) */
        public int $cashSessionId,
        public int $cashRegisterId,
        public string $openingAmount,
        public DateTimeImmutable $openedAt,
        public string $status = 'OPEN',
    ) {
    }
}
