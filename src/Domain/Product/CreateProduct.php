<?php

namespace App\Domain\Product;

use App\Domain\Service\SerializerService;
use Symfony\Component\Uid\Uuid;

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