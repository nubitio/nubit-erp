<?php

namespace App\Product\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface ProductRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?Product;

    public function insert(Product $product): Product;

    public function update(Product $product): Product;

    public function delete(string $id): void;
}