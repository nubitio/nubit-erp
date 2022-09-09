<?php

namespace App\Warehouse\Domain;

interface WarehouseRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(Warehouse $warehouse): void;

    public function update(Warehouse $warehouse): void;

    public function delete(string $id): void;
}