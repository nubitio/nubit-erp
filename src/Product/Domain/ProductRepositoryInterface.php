<?php

namespace App\Product\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ProductRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Product>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Product;

    public function insert(Product $product): Product;

    public function update(Product $product): Product;

    public function delete(string $id): void;
}