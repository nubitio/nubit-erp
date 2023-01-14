<?php

namespace App\Category\Domain;

class CategoryGetOneUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(array $filter): ?Category
    {
        return $this->repository->findOneBy($filter);
    }
}
