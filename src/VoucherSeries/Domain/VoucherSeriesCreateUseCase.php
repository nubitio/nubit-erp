<?php

namespace App\VoucherSeries\Domain;

class VoucherSeriesCreateUseCase
{
    private VoucherSeriesRepositoryInterface $repository;

    public function __construct(VoucherSeriesRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherSeries $voucherSeries): void
    {
        $this->repository->insert($voucherSeries);
    }
}
