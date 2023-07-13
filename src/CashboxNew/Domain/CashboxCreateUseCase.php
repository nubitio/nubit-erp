<?php

namespace App\CashboxNew\Domain;

class CashboxCreateUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Cashbox $cashbox): void
    {
        $this->repository->insert($cashbox);
    }
}
