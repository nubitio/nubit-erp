<?php

namespace App\CashboxNewDetail\Domain;

class CreateCashboxDetailUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CashboxDetail $cashboxNewDetail): CashboxDetail
    {
        return $this->repository->insert($cashboxNewDetail);
    }
}
