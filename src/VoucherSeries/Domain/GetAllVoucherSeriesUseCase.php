<?php

namespace App\VoucherSeries\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllVoucherSeriesUseCase
{
    public function __construct(
        private VoucherSeriesRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<VoucherSeries>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
