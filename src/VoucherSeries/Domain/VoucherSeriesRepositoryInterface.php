<?php

namespace App\VoucherSeries\Domain;

interface VoucherSeriesRepositoryInterface
{
    /**
     * @param array<int, mixed> $filter
     * @return array<string, mixed>
     */
    public function getAll(?array $filter = null): array;

    public function insert(VoucherSeries $voucherSeries): void;

    public function update(VoucherSeries $voucherSeries): void;

    public function delete(int $id): void;
}
