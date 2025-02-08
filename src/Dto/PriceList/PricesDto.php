<?php

namespace App\Dto\PriceList;

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
