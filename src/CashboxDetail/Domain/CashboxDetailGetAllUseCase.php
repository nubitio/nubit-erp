<?php

namespace App\CashboxDetail\Domain;

class CashboxDetailGetAllUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int , array<string, mixed>>|null $filter
     *
     * @return array<string, mixed>
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
