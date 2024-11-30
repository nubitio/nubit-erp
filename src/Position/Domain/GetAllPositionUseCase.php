<?php

namespace App\Position\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPositionUseCase
{
    public function __construct(
        private PositionRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Position>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
