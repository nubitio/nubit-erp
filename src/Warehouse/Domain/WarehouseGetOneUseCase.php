<?php

namespace App\Warehouse\Domain;

class WarehouseGetOneUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Warehouse
    {
        return $this->repository->getOne($id);
    }
}
