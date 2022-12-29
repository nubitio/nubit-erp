<?php

namespace App\Sale\Domain;

class SaleGetAllUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
