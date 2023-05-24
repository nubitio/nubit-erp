<?php

namespace App\Product\Api;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\Product\Domain\ProductStock;

#[ApiResource(
    uriTemplate: '/products/{id}/stock',
    shortName: 'ProductStock',
    operations: [new Get(
        status: 200,
        output: ProductStockDto::class,
    )],
    provider: ProductStockProvider::class
)]
class ProductStockDto extends ProductStock
{
    public function __construct(
        #[ApiProperty(identifier: true)]
        public ?string $id,
        public ?string $name,
        public ?float $stock,
    ) {
        parent::__construct($id, $name, $stock);
    }
}
