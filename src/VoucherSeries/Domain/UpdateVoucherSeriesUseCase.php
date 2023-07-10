<?php

namespace App\VoucherSeries\Domain;

class UpdateVoucherSeriesUseCase
{
    private VoucherSeriesRepositoryInterface $repository;

    public function __construct(VoucherSeriesRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherSeries $voucherSeries): VoucherSeries
    {
        return $this->repository->update($voucherSeries);
    }
}
