<?php

namespace App\Warehouse\Domain;

class WarehouseDeleteUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}