<?php

namespace App\Domain\ProductLocal;

class DeleteProductLocal
{
    private IProductLocalRepository $productLocalRepository;

    public function __construct(IProductLocalRepository $productLocalRepository)
    {
        $this->productLocalRepository = $productLocalRepository;
    }

    public function execute(string $productId, string $localId): void
    {
        $this->productLocalRepository->delete($productId, $localId);
    }
}
