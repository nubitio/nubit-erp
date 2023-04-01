<?php

namespace App\Kardex\Domain;

readonly class KardexGetOneUseCase
{
    public function __construct(
        private KardexRepositoryInterface $kardexRepository
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(?string $warehouseId, ?string $productId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->kardexRepository->getOne($warehouseId, $productId, $dateFrom, $dateTo);
    }
}
