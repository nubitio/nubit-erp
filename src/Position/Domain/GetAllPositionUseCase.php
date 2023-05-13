<?php

namespace App\Position\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPositionUseCase
{
    public function __construct(
        private PositionRepositoryInterface $repository
    ) {
    }

    /**
     * @return PaginatorInterface<Position>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
