<?php

namespace App\Domain\Product;

class GetAllProducts
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(?string $filter = null): array
    {
        return $this->productRepository->getAll($filter);
    }
}