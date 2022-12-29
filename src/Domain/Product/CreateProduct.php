<?php

namespace App\Domain\Product;

class CreateProduct
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(Product $product): void
    {
        $this->productRepository->insert($product);
    }
}
