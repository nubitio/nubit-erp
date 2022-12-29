<?php

namespace App\Dashboard\Domain;

class SalesDailyUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository
    ) {
    }

    public function __invoke(string $saleId): array
    {
        return $this->repository->getDailySales($saleId);
    }
}
