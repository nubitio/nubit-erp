<?php

namespace App\Cashbox\Domain;

class CashboxGetLastUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $localId): ?Cashbox
    {
        return $this->repository->getLast($localId);
    }
}
