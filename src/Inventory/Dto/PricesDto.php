<?php

declare(strict_types=1);

namespace App\Inventory\Dto;

class PricesDto
{
    public function __construct(
        public int $priceListId,
        /**
         * @var PriceItemDto[]
         */
        public array $prices,
    ) {
    }
}
