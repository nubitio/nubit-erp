<?php

namespace App\Warehouse\Domain;

class WarehouseCreateUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Warehouse $warehouse): Warehouse
    {
        return $this->repository->insert($warehouse);
    }
}
