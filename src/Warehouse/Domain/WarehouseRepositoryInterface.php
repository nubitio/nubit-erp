<?php

namespace App\Warehouse\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface WarehouseRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Warehouse>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Warehouse;

    public function insert(Warehouse $warehouse): Warehouse;

    public function update(Warehouse $warehouse): Warehouse;

    public function delete(string $id): void;

    /** @return Warehouse[] */
    public function getAllByBranch(string $branchId): array;
}