<?php

namespace App\Product\Domain;

interface ProductRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Product $product): void;

    public function update(Product $product): void;

    public function delete(string $id): void;
}
