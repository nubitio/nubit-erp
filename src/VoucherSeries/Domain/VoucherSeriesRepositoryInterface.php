<?php

namespace App\VoucherSeries\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

interface VoucherSeriesRepositoryInterface
{
    /**
     * @return PaginatorInterface<VoucherSeries>
     */
    public function getAll(Options $options): PaginatorInterface;

    public function getOne(int $id): ?VoucherSeries;

    public function insert(VoucherSeries $voucherSeries): VoucherSeries;

    public function update(VoucherSeries $voucherSeries): VoucherSeries;

    public function delete(int $id): void;
}
