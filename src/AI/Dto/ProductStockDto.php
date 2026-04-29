<?php

declare(strict_types=1);

namespace App\AI\Dto;

final readonly class ProductStockDto
{
    /**
     * @param ProductWarehouseStockDto[] $warehouses
     */
    public function __construct(
        public string $productId,
        public ?string $code,
        public string $name,
        public float $totalStock,
        public ?float $fallbackStock,
        public array $warehouses,
    ) {
    }
}
