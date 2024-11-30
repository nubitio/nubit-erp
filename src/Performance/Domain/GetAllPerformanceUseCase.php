<?php

namespace App\Performance\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

readonly class GetAllPerformanceUseCase
{
    public function __construct(
        private PerformanceRepositoryInterface $repository,
    ) {
    }

    /**
     * @return PaginatorInterface<Performance>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
