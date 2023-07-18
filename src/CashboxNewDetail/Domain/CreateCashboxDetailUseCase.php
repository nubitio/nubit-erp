<?php

namespace App\CashboxNewDetail\Domain;

class CreateCashboxDetailUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CashboxDetail $cashboxDetail): CashboxDetail
    {
        return $this->repository->insert($cashboxDetail);
    }
}
