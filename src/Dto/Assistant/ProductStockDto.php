<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

/**
 * @phpstan-import-type ProductWarehouseStockArray from ProductWarehouseStockDto
 */
final class ProductStockDto
{
    /**
     * @param ProductWarehouseStockDto[] $warehouses
     */
    public function __construct(
        public readonly string $productId,
        public readonly ?string $productCode,
        public readonly string $productName,
        public readonly float $totalStock,
        public readonly ?float $fallbackStock,
        public readonly array $warehouses = [],
    ) {
    }
}
