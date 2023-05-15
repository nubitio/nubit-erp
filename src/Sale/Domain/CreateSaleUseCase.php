<?php

namespace App\Sale\Domain;

class CreateSaleUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Sale $sale): Sale
    {
        return $this->repository->insert($sale);
    }
}
