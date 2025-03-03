<?php

namespace App\Dashboard\Domain;

readonly class BillingMonthlyUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(): array
    {
        return $this->repository->getMonthlyInvoicing();
    }
}
