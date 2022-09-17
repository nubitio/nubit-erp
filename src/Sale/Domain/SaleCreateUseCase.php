<?php

namespace App\Sale\Domain;

class SaleCreateUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Sale $sale): void
    {
        $this->repository->insert($sale);
    }
}