<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\ApiProperty;
use App\Product\Domain\Product;

class ProductDto extends Product
{
    #[ApiProperty(identifier: true)]
    public ?string $producto_id;
}
