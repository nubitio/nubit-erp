<?php

namespace App\Kardex\Domain;

class KardexGetAllUseCase
{
    public function __construct(
        private readonly KardexRepositoryInterface $kardexRepository
    )
    {
    }

    public function __invoke(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->kardexRepository->getAll($localId, $warehouseId, $categoryId, $dateFrom, $dateTo);
    }
}