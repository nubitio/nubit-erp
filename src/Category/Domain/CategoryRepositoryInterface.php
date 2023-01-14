<?php

namespace App\Category\Domain;

interface CategoryRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function findOneBy(array $filter): ?Category;

    public function insert(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(string $id): void;
}
