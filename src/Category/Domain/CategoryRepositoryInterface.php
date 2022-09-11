<?php

namespace App\Category\Domain;

interface CategoryRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Category $category): void;

    public function update(Category $category): void;

    public function delete(string $id): void;
}