<?php

namespace App\Supplier\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface SupplierRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?Supplier;

    public function insert(Supplier $supplier): Supplier;

    public function update(Supplier $supplier): Supplier;

    public function delete(string $id): void;
}