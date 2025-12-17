<?php

declare(strict_types=1);

namespace App\Dto\Assistant;

final readonly class ProductStockDto
{
    /**
     * @param ProductWarehouseStockDto[] $warehouses
     */
    public function __construct(
        public string $productId,
        public ?string $productCode,
        public string $productName,
        public float $totalStock,
        public ?float $fallbackStock,
        public array $warehouses = [],
    ) {
    }
}
