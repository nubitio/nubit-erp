<?php

namespace App\Product\Domain;

class GetOneProductUseCase
{
    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Product
    {
        return $this->repository->getOne($id);
    }
}