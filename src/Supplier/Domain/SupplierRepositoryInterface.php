<?php

namespace App\Supplier\Domain;

interface SupplierRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(Supplier $supplier): void;

    public function update(Supplier $supplier): void;

    public function delete(string $id): void;
}
