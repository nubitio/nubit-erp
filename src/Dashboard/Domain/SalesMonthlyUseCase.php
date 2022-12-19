<?php

namespace App\Dashboard\Domain;

class SalesMonthlyUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository
    )
    {
    }

    public function __invoke(string $localId): array
    {
        return $this->repository->getMonthlySales($localId);
    }
}