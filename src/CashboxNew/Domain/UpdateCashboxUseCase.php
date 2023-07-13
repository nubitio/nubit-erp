<?php

namespace App\CashboxNew\Domain;

class UpdateCashboxUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Cashbox $cashbox): Cashbox
    {
        return $this->repository->update($cashbox);
    }
}
