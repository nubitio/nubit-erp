<?php

declare(strict_types=1);

namespace App\Dto\CashRegister;

class CashRegisterOpenDto
{
    public function __construct(
        public int $id,
        public string|float $openingBalance,
    ) {
    }
}
