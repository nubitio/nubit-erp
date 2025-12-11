<?php

declare(strict_types=1);

namespace App\Dto\PriceList;

class PriceItemDto
{
    public function __construct(
        public ?string $productId = null,
        public ?float $price = null,
    ) {
    }
}
