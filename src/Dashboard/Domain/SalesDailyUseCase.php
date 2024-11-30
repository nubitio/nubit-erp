<?php

namespace App\Dashboard\Domain;

readonly class SalesDailyUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $saleId): array
    {
        return $this->repository->getDailySales($saleId);
    }
}
