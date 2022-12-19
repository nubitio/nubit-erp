<?php

namespace App\Dashboard\Domain;

class BillingMonthlyUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository
    )
    {
    }

    public function __invoke(string $localId): array
    {
        return $this->repository->getMonthlyInvoicing($localId);
    }
}