<?php

namespace App\Supplier\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface SupplierRepositoryInterface
{
    /**
     * @return PaginatorInterface<Supplier>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?Supplier;

    public function insert(Supplier $supplier): Supplier;

    public function update(Supplier $supplier): Supplier;

    public function delete(string $id): void;
}
