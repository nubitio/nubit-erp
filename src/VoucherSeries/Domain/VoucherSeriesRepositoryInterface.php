<?php

namespace App\VoucherSeries\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

interface VoucherSeriesRepositoryInterface
{
    /**
     * @return PaginatorInterface<VoucherSeries>
     */
    public function getAll(LoadOptions $options): PaginatorInterface;

    public function getOne(int $id): ?VoucherSeries;

    public function insert(VoucherSeries $voucherSeries): VoucherSeries;

    public function update(VoucherSeries $voucherSeries): VoucherSeries;

    public function delete(int $id): void;
}
