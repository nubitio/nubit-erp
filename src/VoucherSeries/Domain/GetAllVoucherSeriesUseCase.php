<?php

namespace App\VoucherSeries\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllVoucherSeriesUseCase
{
    public function __construct(
        private VoucherSeriesRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<VoucherSeries>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
