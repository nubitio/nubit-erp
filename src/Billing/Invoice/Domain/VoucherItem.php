<?php

declare(strict_types=1);

namespace App\Billing\Invoice\Domain;

class VoucherItem
{
    public function __construct(
        public ?string $product_id = null,
        public ?string $product_code = null,
        public ?string $product_uom = null,
        public ?string $product_name = null,
        public ?string $quantity = null,
        public ?string $unit_value = null,
        public ?string $unit_price = null,
        public ?string $discount_amount = null,
        public ?string $discount_percentage = null,
        public ?string $taxed_amount = null,
        public ?string $tax_amount = null,
        public ?string $tax_percentage = null,
        public ?string $tax_type = null,
        public ?string $total = null,
    ) {
    }
}
