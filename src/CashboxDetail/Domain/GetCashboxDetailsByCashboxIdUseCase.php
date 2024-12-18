<?php

namespace App\CashboxDetail\Domain;

class GetCashboxDetailsByCashboxIdUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $cashboxId): array
    {
        return $this->repository->getByCashboxId($cashboxId);
    }
}
