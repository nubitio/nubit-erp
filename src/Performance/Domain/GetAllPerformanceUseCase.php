<?php

namespace App\Performance\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPerformanceUseCase
{
    public function __construct(
        private PerformanceRepositoryInterface $repository
    )
    {
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<Performance>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}