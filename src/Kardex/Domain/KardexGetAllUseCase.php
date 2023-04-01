<?php

namespace App\Kardex\Domain;

readonly class KardexGetAllUseCase
{
    public function __construct(
        private KardexRepositoryInterface $kardexRepository
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $localId, ?string $warehouseId, ?string $categoryId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->kardexRepository->getAll($localId, $warehouseId, $categoryId, $dateFrom, $dateTo);
    }
}
