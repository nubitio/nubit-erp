<?php

namespace App\Warehouse\Domain;

readonly class GetAllByBranchUseCase
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    )
    {
    }

    public function __invoke(string $branchId): array
    {
        return $this->warehouseRepository->getAllByBranch($branchId);
    }
}