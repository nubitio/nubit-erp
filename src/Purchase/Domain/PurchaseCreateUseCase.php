<?php

namespace App\Purchase\Domain;

class PurchaseCreateUseCase
{
    private PurchaseRepositoryInterface $repository;

    public function __construct(PurchaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Purchase $purchase): void
    {
        $this->repository->insert($purchase);
    }
}