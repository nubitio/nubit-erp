<?php

namespace App\Supplier\Domain;

class UpdateSupplierUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Supplier $supplier): Supplier
    {
        return $this->repository->update($supplier);
    }
}