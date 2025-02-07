<?php

namespace App\Dto\Sale;

class VoidSaleDto
{
    public function __construct(
        public string $saleId,
        public string $reason,
    ) {
    }
}
