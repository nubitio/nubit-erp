<?php

namespace App\SaleDetail\Domain;

class SaleDetailUpdateUseCase
{
    private SaleDetailRepositoryInterface $repository;

    public function __construct(SaleDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(SaleDetail $saledetail): void
    {
        $this->repository->update($saledetail);
    }
}