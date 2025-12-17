<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final class ProductWarehouseStockDto
{
    public function __construct(
        public readonly string $warehouseId,
        public readonly string $warehouseName,
        public readonly float $stock,
        public readonly float $minStock,
    ) {
    }
}
