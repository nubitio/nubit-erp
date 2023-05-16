<?php

namespace App\Warehouse\Domain;

readonly class GetAllByBranchUseCase
{
    public function __construct(
        private WarehouseRepositoryInterface $warehouseRepository,
    ) {
    }

    /** @return Warehouse[] */
    public function __invoke(string $branchId): array
    {
        return $this->warehouseRepository->getAllByBranch($branchId);
    }
}
