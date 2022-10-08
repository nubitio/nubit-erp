<?php

namespace App\CashboxDetail\Domain;

class CashboxDetailUpdateUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(CashboxDetail $cashboxDetail): void
    {
        $this->repository->update($cashboxDetail);
    }
}