<?php

namespace App\Product\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface ProductRepositoryInterface
{
    /**
     * @return PaginatorInterface<Product>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Product;

    public function insert(Product $product): Product;

    public function update(Product $product): Product;

    public function delete(string $id): void;

    public function getStock(string $productId, string $warehouseId): ProductStock;
}
