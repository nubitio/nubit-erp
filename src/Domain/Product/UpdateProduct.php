<?php

namespace App\Domain\Product;

class UpdateProduct
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(array $data): void
    {
        $this->productRepository->update($data);
    }
}