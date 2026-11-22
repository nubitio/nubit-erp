<?php

declare(strict_types=1);

namespace App\Inventory\Dto;

/**
 * Output DTO for GET /api/price-lists/{id}/prices.
 *
 * `price` is kept as a string to preserve exact decimal representation
 * from the database without floating-point rounding errors.
 *
 * `priceSource` indicates how the effective price was computed:
 *   - 'override'   a special price stored for the product in the list
 *   - 'multiplier' base_price × price_list.multiplier
 *   - 'base'       product base price (fallback)
 */
class PriceListDto
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?string $sku = null,
        public ?string $code = null,
        public ?string $price = null,
        public string $priceSource = 'base',
    ) {}
}
