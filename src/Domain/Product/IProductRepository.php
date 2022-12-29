<?php

namespace App\Domain\Product;

interface IProductRepository
{
    public function getAll(?array $filter = null): array;

    public function getAllForExport(string $localId): array;

    public function getProductList(string $localId): array;

    public function getById(string $id): Product;

    public function insert(Product $product): void;

    public function update(Product $product): void;

    public function delete(string $id): void;
}
