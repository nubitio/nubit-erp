<?php

namespace App\Warehouse\Domain;

class WarehouseUpdateUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Warehouse $warehouse): void
    {
        $this->repository->update($warehouse);
    }
}
