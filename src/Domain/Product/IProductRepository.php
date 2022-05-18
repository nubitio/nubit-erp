<?php

namespace App\Domain\Product;

interface IProductRepository
{
    public function getAll(): array;

    public function getById(int $id): array;

    public function getByName(string $name): array;

    public function getByCategory(string $category): array;

    public function insert(Product $product): void;

    public function update(array $product): void;

    public function delete(string $id): void;
}