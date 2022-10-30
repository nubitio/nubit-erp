<?php

namespace App\Sale\Domain;

class SaleVoidUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Sale $sale): void
    {
        $this->repository->void($sale);
    }
}