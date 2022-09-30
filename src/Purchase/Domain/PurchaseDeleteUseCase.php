<?php

namespace App\Purchase\Domain;

class PurchaseDeleteUseCase
{
    private PurchaseRepositoryInterface $repository;

    public function __construct(PurchaseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}