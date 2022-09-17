<?php

namespace App\VoucherSeries\Domain;

interface VoucherSeriesRepositoryInterface
{
    public function getAll(?string $filter = null): array;

    public function insert(VoucherSeries $voucherseries): void;

    public function update(VoucherSeries $voucherseries): void;

    public function delete(string $id): void;
}