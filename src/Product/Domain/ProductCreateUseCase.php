<?php

namespace App\Product\Domain;

class ProductCreateUseCase
{
    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Product $product): void
    {
        $this->repository->insert($product);
    }
}