<?php

namespace App\Sale\Domain;

class UpdateSaleUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Sale $sale): Sale
    {
        return $this->repository->update($sale);
    }
}