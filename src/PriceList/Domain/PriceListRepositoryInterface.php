<?php

namespace App\PriceList\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\PriceListDetail\Domain\PriceListDetail;

interface PriceListRepositoryInterface
{
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(string $id): ?PriceList;

    public function insert(PriceList $priceList): PriceList;

    public function update(PriceList $priceList): PriceList;

    public function delete(string $id): void;

    public function getPricesByList(int $id): array;

    /**
     * @param PriceListDetail[] $prices
     */
    public function savePrices(array $prices): void;
}