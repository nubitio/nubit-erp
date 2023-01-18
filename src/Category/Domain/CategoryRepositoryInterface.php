<?php

namespace App\Category\Domain;

use App\Core\Infrastructure\Common\DataLoader\Options;

interface CategoryRepositoryInterface
{
    /**
     * @return Category[]
     */
    public function getAll(Options $options): array;

    public function findOneBy(array $filter): ?Category;

    public function insert(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(string $id): void;
}
