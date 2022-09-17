<?php

namespace App\Domain\ProductLocal;

class CreateProductLocal
{
    private IProductLocalRepository $productLocalRepository;

    public function __construct(IProductLocalRepository $productLocalRepository)
    {
        $this->productLocalRepository = $productLocalRepository;
    }

    public function execute(ProductLocal $productLocal): void
    {
        $this->productLocalRepository->add($productLocal);
    }
}