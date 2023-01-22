<?php

namespace App\Warehouse\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface WarehouseRepositoryInterface
{
    public function getAll(Options $options): DataStoreInterface;

    public function getOne(string $id): ?Warehouse;

    public function insert(Warehouse $warehouse): Warehouse;

    public function update(Warehouse $warehouse): Warehouse;

    public function delete(string $id): void;
}