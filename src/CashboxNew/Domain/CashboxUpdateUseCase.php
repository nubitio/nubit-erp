<?php

namespace App\CashboxNew\Domain;

class CashboxUpdateUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Cashbox $cashbox): void
    {
        $this->repository->update($cashbox);
    }
}
