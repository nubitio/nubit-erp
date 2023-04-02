<?php

namespace App\Category\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

interface CategoryRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Category>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Category;

    public function insert(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(string $id): void;
}
