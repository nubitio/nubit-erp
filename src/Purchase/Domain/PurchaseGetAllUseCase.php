<?php

namespace App\Purchase\Domain;

class PurchaseGetAllUseCase
{
    private PurchaseRepositoryInterface $repository;

    public function __construct(PurchaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}