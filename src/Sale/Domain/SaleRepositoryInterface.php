<?php

namespace App\Sale\Domain;

interface SaleRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function getOneById(string $id): array;

    public function getOne(string $id): Sale;

    public function insert(Sale $sale): void;

    public function update(Sale $sale): void;

    public function delete(string $id): void;

    public function void(Sale $sale): void;
}
