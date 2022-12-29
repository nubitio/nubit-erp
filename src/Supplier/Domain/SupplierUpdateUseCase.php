<?php

namespace App\Supplier\Domain;

class SupplierUpdateUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Supplier $supplier): void
    {
        $this->repository->update($supplier);
    }
}
