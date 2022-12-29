<?php

namespace App\Domain\Product;

class ExportProducts
{
    private IProductRepository $productRepository;

    public function __construct(IProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function execute(string $localId): array
    {
        return $this->productRepository->getAllForExport($localId);
    }
}
