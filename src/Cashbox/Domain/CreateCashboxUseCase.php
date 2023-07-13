<?php

namespace App\Cashbox\Domain;

class CreateCashboxUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Cashbox $cashbox): Cashbox
    {
        return $this->repository->insert($cashbox);
    }
}
