<?php

namespace App\SaleDetail\Domain;

interface SaleDetailRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(SaleDetail $saledetail): void;

    public function update(SaleDetail $saledetail): void;

    public function delete(string $id): void;
}
