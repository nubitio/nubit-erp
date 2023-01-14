<?php

namespace App\Category\Domain;

class CategoryCreateUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Category $category): Category
    {
        return $this->repository->insert($category);
    }
}
