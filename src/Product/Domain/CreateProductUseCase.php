<?php

namespace App\Product\Domain;

class CreateProductUseCase
{
    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Product $product): Product
    {
        return $this->repository->insert($product);
    }
}