<?php

namespace App\Purchase\Domain;

class PurchaseUpdateUseCase
{
    private PurchaseRepositoryInterface $repository;

    public function __construct(PurchaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Purchase $purchase): void
    {
        $this->repository->update($purchase);
    }
}