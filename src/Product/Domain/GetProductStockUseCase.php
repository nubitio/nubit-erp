<?php

namespace App\Product\Domain;

readonly class GetProductStockUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository
    ) {
    }

    public function __invoke(string $productId, string $warehouseId): ProductStock
    {
        return $this->productRepository->getStock($productId, $warehouseId);
    }
}
