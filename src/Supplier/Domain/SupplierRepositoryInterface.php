<?php

namespace App\Supplier\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface SupplierRepositoryInterface
{
    /**
     * @return PaginatorInterface<Supplier>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Supplier;

    public function insert(Supplier $supplier): Supplier;

    public function update(Supplier $supplier): Supplier;

    public function delete(string $id): void;
}
