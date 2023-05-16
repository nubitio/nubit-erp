<?php

namespace App\PriceListDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPriceListDetailUseCase
{
    public function __construct(
        private PriceListDetailRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<PriceListDetail>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
