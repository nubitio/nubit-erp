<?php

namespace App\ProductionDetail\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllProductionDetailUseCase
{
    public function __construct(
        private ProductionDetailRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<ProductionDetail>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
