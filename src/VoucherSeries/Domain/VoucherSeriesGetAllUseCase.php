<?php

namespace App\VoucherSeries\Domain;

class VoucherSeriesGetAllUseCase
{
    private VoucherSeriesRepositoryInterface $repository;

    public function __construct(VoucherSeriesRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param array<int, mixed>|null $filter
     * @return array<string, mixed>
     */
    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
