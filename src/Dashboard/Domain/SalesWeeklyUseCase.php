<?php

namespace App\Dashboard\Domain;

class SalesWeeklyUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository
    ) {
    }

    public function __invoke(string $localId): array
    {
        return $this->repository->getWeeklySales($localId);
    }
}
