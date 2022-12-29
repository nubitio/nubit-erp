<?php

namespace App\Supplier\Domain;

class SupplierCreateUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Supplier $supplier): void
    {
        $this->repository->insert($supplier);
    }
}
