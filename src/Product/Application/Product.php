<?php

namespace App\Product\Application;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Product
{
    public function __construct(
        public string $name,
        public string $description,
        public float  $price,
        public int    $stock,
    )
    {
    }
}