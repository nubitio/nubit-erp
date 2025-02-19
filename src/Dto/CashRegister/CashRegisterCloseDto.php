<?php

namespace App\Dto\CashRegister;

use App\Entity\CashClosure;

class CashRegisterCloseDto
{
    public function __construct(
        /**
         * @var CashClosure[]
         */
        public array $closureItems,
    ) {
    }
}
