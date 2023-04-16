<?php

namespace App\Invoice\Domain;

class VoucherItem
{
    public function __construct(
        public ?string $product_id,
        public ?string $product_code,
        public ?string $product_name,
        public ?string $quantity,
        public ?string $unit_value,
        public ?string $unit_price,
        public ?string $discount_amount,
        public ?string $discount_percentage,
        public ?string $taxed_amount,
        public ?string $tax_amount,
        public ?string $tax_percentage,
        public ?string $tax_type,
        public ?string $total
    )
    {
    }
}