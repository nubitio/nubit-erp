<?php

namespace App\Cashbox\Domain;

class CashboxGetAllUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int , array<string, mixed>>|null $filter
     * @return Cashbox[]
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
