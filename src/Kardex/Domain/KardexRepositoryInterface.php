<?php

namespace App\Kardex\Domain;

interface KardexRepositoryInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getAll(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array;

    /**
     * @return array<int, mixed>
     */
    public function getOne(?string $warehouseId, ?string $productId, ?string $dateFrom, ?string $dateTo): array;

    /**
     * @return array<Kardex>
     */
    public function getKardex(): array;
}
