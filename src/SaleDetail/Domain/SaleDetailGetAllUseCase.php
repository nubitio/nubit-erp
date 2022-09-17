<?php

namespace App\SaleDetail\Domain;

class SaleDetailGetAllUseCase
{
    private SaleDetailRepositoryInterface $repository;

    public function __construct(SaleDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}