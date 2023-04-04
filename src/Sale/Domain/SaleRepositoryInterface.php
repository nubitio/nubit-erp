<?php

namespace App\Sale\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface SaleRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<Sale>
     */
    public function getAll(Options $options): PaginatorInterface;

    /**
     * @return array<string, array<int|string, mixed>>
     */
    public function getOneById(string $id): array;

    public function getOne(string $id): Sale;

    public function insert(Sale $sale): Sale;

    public function update(Sale $sale): Sale;

    public function delete(string $id): void;

    public function void(Sale $sale): void;
}
