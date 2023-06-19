<?php

namespace App\ProductionDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllProductionDetailUseCase
{
    public function __construct(
        private ProductionDetailRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<ProductionDetail>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
