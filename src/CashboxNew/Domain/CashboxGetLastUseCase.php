<?php

namespace App\CashboxNew\Domain;

class CashboxGetLastUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $localId, int $userId): ?Cashbox
    {
        return $this->repository->getLast($localId, $userId);
    }
}
