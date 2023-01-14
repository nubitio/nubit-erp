<?php

namespace App\Category\Domain;

class CategoryUpdateUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Category $category): Category
    {
        return $this->repository->update($category);
    }
}
