<?php

namespace App\Warehouse\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface WarehouseRepositoryInterface
{
    /**
     * @return PaginatorInterface<Warehouse>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Warehouse;

    public function insert(Warehouse $warehouse): Warehouse;

    public function update(Warehouse $warehouse): Warehouse;

    public function delete(string $id): void;

    /** @return Warehouse[] */
    public function getAllByBranch(string $branchId): array;
}
