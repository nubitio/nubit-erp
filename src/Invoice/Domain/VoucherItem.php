<?php

namespace App\Invoice\Domain;

class VoucherItem
{
    public function __construct(
        public ?string $product_id = null,
        public ?string $product_code = null,
        public ?string $product_name = null,
        public ?float $quantity = null,
        public ?float $unit_value = null,
        public ?float $unit_price = null,
        public ?float $discount_amount = null,
        public ?float $discount_percentage = null,
        public ?float $taxed_amount = null,
        public ?float $tax_amount = null,
        public ?float $tax_percentage = null,
        public ?string $tax_type = null,
        public ?float $total = null
    ) {
    }
}
