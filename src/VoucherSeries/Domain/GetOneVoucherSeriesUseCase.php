<?php

namespace App\VoucherSeries\Domain;

class GetOneVoucherSeriesUseCase
{
    private VoucherSeriesRepositoryInterface $repository;

    public function __construct(VoucherSeriesRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?VoucherSeries
    {
        return $this->repository->getOne($id);
    }
}
