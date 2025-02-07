<?php

namespace App\Dto\PriceList;

class PriceItemDto
{
    public function __construct(
        public ?string $productId = null,
        public ?float $price = null,
    ) {
    }
}
