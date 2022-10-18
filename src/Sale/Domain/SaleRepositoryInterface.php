<?php

namespace App\Sale\Domain;

interface SaleRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function getOneById(string $id): array;

    public function insert(Sale $sale): void;

    public function update(Sale $sale): void;

    public function delete(string $id): void;
}