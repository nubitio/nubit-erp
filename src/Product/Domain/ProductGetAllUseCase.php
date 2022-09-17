<?php

namespace App\Product\Domain;

class ProductGetAllUseCase
{
    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}