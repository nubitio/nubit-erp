<?php

declare(strict_types=1);

namespace App\Sales\Dto;

class SaleQuoteSendDto
{
    public function __construct(
        public string $id,
    ) {}
}
