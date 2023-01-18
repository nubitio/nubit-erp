<?php

namespace App\Category\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface CategoryRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function findOneBy(array $filter): ?Category;

    public function insert(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(string $id): void;
}
