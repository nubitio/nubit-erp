<?php

namespace App\Kardex\Domain;

interface KardexRepositoryInterface
{
    public function getAll(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array;

    public function getOne(?string $warehouseId, ?string $productId, ?string $dateFrom, ?string $dateTo): array;
}