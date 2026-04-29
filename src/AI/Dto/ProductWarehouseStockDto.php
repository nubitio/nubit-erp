<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class ProductWarehouseStockDto
{
    public function __construct(
        public string $warehouseId,
        public string $warehouseName,
        public float $stock,
        public float $minStock,
    ) {
    }
}
