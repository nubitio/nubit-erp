<?php

namespace App\Domain\ProductLocal;

class UpdateProductLocal
{
    private IProductLocalRepository $productLocalRepository;

    public function __construct(IProductLocalRepository $productLocalRepository)
    {
        $this->productLocalRepository = $productLocalRepository;
    }

    public function execute(ProductLocal $productLocal): void
    {
        $this->productLocalRepository->update($productLocal);
    }
}