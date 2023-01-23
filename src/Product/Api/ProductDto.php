<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use App\Product\Domain\Product;

#[ApiResource(
    shortName: 'Product',
    provider: ProductProvider::class,
    processor: ProductProcessor::class,
)]
class ProductDto extends Product
{
    #[ApiProperty(identifier: true)]
    public ?string $producto_id;
}