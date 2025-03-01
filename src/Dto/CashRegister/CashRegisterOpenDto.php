<?php

namespace App\Dto\CashRegister;

class CashRegisterOpenDto
{
    public function __construct(
        public int $id,
        public float $openingBalance,
    ) {
    }
}
