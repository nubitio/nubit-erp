<?php

namespace App\PriceList\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;
use App\PriceListDetail\Domain\PriceListDetail;

interface PriceListRepositoryInterface
{
    /**
     * @return PaginatorInterface<PriceList>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(string $id): ?PriceList;

    public function insert(PriceList $priceList): PriceList;

    public function update(PriceList $priceList): PriceList;

    public function delete(int $id): void;

    /**
     * @return array<int, mixed>
     */
    public function getPricesByList(int $id): array;

    /**
     * @param PriceListDetail[] $prices
     */
    public function savePrices(array $prices): void;
}
