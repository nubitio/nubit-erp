<?php

namespace App\PriceList\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\PriceListDetail\Domain\PriceListDetail;

interface PriceListRepositoryInterface
{
    /**
     * @param Options $options
     * @return PaginatorInterface<PriceList>
     */
    public function getAll(Options $options): PaginatorInterface;

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