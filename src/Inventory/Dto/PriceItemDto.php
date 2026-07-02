<?php

declare(strict_types=1);

namespace App\Inventory\Dto;

class PriceItemDto
{
    public function __construct(
        public ?string $productId = null,
        public ?string $price = null,
    ) {}
}
