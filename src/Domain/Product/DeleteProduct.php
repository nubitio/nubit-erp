<?php

namespace App\Domain\Product;

class DeleteProduct
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $id): void
    {
        $this->productRepository->delete($id);
    }
}