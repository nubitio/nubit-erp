<?php

declare(strict_types=1);

namespace App\Inventory\Dto;

/**
 * Slim DTO returned by GET /api/price-lists/{id}/apply.
 *
 * Used by the sale form to auto-populate line item prices when a price list is selected.
 * Returns only the product ID and its effective price — no extra metadata needed.
 *
 * `price` is a string to preserve exact decimal precision from the database.
 */
class PriceListApplyDto
{
    public function __construct(
        public string $id,
        public string $price,
    ) {
    }
}
