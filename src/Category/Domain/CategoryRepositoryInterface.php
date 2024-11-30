<?php

namespace App\Category\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface CategoryRepositoryInterface
{
    /**
     * @return PaginatorInterface<Category>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Category;

    public function insert(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(string $id): void;
}
