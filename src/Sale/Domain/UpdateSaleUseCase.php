<?php

namespace App\Sale\Domain;

class UpdateSaleUseCase
{
    public function __construct(private readonly SaleRepositoryInterface $repository)
    {
    }

    public function __invoke(Sale $sale): Sale
    {
        return $this->repository->update($sale);
    }
}
