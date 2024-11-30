<?php

namespace App\Product\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface ProductRepositoryInterface
{
    /**
     * @return PaginatorInterface<Product>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Product;

    public function insert(Product $product): Product;

    public function update(Product $product): Product;

    public function delete(string $id): void;

    public function getStock(string $productId, string $warehouseId): ProductStock;
}
