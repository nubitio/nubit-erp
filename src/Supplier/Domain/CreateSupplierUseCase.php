<?php

namespace App\Supplier\Domain;

class CreateSupplierUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Supplier $supplier): Supplier
    {
        return $this->repository->insert($supplier);
    }
}
