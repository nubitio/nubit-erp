<?php

declare(strict_types=1);

namespace App\Sales\Dto;

class VoidSaleDto
{
    public function __construct(
        public string $saleId,
        public string $reason,
    ) {
    }
}
