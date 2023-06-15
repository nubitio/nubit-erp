<?php

namespace App\Production\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllProductionUseCase
{
    public function __construct(
        private ProductionRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Production>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
