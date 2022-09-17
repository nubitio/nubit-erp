<?php

namespace App\VoucherSeries\Domain;

class VoucherSeriesUpdateUseCase
{
    private VoucherSeriesRepositoryInterface $repository;

    public function __construct(VoucherSeriesRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(VoucherSeries $voucherseries): void
    {
        $this->repository->update($voucherseries);
    }
}