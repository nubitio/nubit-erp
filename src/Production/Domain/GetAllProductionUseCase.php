<?php

namespace App\Production\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllProductionUseCase
{
    public function __construct(
        private ProductionRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Production>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
