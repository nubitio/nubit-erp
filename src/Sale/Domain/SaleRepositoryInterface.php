<?php

namespace App\Sale\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface SaleRepositoryInterface
{
    /**
     * @return PaginatorInterface<Sale>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): Sale;

    public function insert(Sale $sale): Sale;

    public function update(Sale $sale): Sale;

    public function delete(string $id): void;

    public function void(Sale $sale): void;

    public function getSaleByCustomerId(string $id): PaginatorInterface;
}
