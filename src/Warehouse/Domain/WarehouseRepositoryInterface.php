<?php

namespace App\Warehouse\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface WarehouseRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Warehouse;

    public function insert(Warehouse $warehouse): Warehouse;

    public function update(Warehouse $warehouse): Warehouse;

    public function delete(string $id): void;

    /** @return Warehouse[] */
    public function getAllByBranch(string $branchId): array;
}