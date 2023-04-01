<?php

namespace App\Dashboard\Domain;

readonly class SalesWeeklyUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $localId): array
    {
        return $this->repository->getWeeklySales($localId);
    }
}
