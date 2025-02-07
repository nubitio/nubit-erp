<?php

namespace App\Dto\PriceList;

class PricesDto
{
    public function __construct(
        public ?int $priceListId = null,
        /**
         * @var PriceItemDto[]
         */
        public array $prices,
    ) {
    }
}
