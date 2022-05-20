<?php

namespace App\Domain\Product;

class GetProductList
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $localId): array
    {
        return $this->productRepository->getProductList($localId);
    }
}