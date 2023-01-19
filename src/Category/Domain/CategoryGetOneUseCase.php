<?php

namespace App\Category\Domain;

class CategoryGetOneUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Category
    {
        return $this->repository->getOne($id);
    }
}
