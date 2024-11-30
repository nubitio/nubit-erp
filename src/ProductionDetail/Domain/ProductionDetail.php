<?php

namespace App\ProductionDetail\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;
use App\Product\Domain\Product;
use Symfony\Component\Serializer\Annotation\SerializedName;

#[IgnoreAutowire]
class ProductionDetail
{
    public function __construct(
        #[SerializedName('id')]
        public ?int $id = null,
        #[SerializedName('production_id')]
        public ?int $production_id = null,
        #[SerializedName('material')]
        public ?Product $material = null,
        #[SerializedName('quantity')]
        public ?float $quantity = null,
    ) {
    }
}
