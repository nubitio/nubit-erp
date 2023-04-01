<?php

namespace App\Sale\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

interface SaleRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getOneById(string $id): array;

    public function getOne(string $id): ?Sale;

    public function insert(Sale $sale): Sale;

    public function update(Sale $sale): Sale;

    public function delete(string $id): void;

    public function void(Sale $sale): void;
}
