<?php

namespace App\CashboxNewDetail\Domain;

class UpdateCashboxDetailUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CashboxDetail $cashboxNewDetail): CashboxDetail
    {
        return $this->repository->update($cashboxNewDetail);
    }
}
