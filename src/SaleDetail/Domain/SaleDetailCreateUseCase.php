<?php

namespace App\SaleDetail\Domain;

class SaleDetailCreateUseCase
{
    private SaleDetailRepositoryInterface $repository;

    public function __construct(SaleDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(SaleDetail $saledetail): void
    {
        $this->repository->insert($saledetail);
    }
}
