<?php

declare(strict_types=1);

namespace App\Dto\PriceList;

class PriceListDto
{
    public function __construct(
        public ?int $id = null,
        public ?string $name = null,
        public ?float $price = null,
    ) {
    }
}
