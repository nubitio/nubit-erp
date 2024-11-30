<?php

namespace App\PriceListDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPriceListDetailUseCase
{
    public function __construct(
        private PriceListDetailRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<PriceListDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
