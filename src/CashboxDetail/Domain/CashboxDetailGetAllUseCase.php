<?php

namespace App\CashboxDetail\Domain;

class CashboxDetailGetAllUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}