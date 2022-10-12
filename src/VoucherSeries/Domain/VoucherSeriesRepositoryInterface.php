<?php

namespace App\VoucherSeries\Domain;

interface VoucherSeriesRepositoryInterface
{
    public function getAll(?array $filter = null): array;

    public function insert(VoucherSeries $voucherSeries): void;

    public function update(VoucherSeries $voucherSeries): void;

    public function delete(string $id): void;
}