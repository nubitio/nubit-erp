<?php

namespace App\Category\Domain;

class CategoryUpdateUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Category $category): void
    {
        $this->repository->update($category);
    }
}