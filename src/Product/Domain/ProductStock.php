<?php

namespace App\Product\Domain;

use App\Core\Domain\Annotation\IgnoreAutowire;

#[IgnoreAutowire]
class ProductStock
{
    public function __construct(
        public ?string $id = null,
        public ?string $name = null,
        public ?float $stock = null
    ) {
    }
}
