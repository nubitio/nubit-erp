<?php

namespace App\Kardex\Domain;

use Symfony\Component\Serializer\Annotation\SerializedName;

class Kardex
{
    public function __construct(
        #[SerializedName('product_id')]
        public ?string $productId = null,
        #[SerializedName('product_code')]
        public ?string $productCode = null,
        #[SerializedName('product_name')]
        public ?string $productName = null,
        public ?int $income = null,
        public ?int $outcome = null,
        public ?int $stock = null,
    ) {
    }
}
