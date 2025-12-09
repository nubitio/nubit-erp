<?php

namespace App\Dto\CashRegister;

class CashRegisterCloseDto
{
    public function __construct(
        public float $expectedAmount,
        public float $countedAmount,
        public float $difference,
        public float $note
    ) {
    }
}
