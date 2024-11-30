<?php

namespace App\Dashboard\Domain;

readonly class SalesMonthlyUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $localId): array
    {
        return $this->repository->getMonthlySales($localId);
    }
}
