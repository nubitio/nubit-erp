<?php

namespace App\Supplier\Domain;

class GetOneSupplierUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Supplier
    {
        return $this->repository->getOne($id);
    }
}
