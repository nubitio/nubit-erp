<?php

namespace App\Kardex\Domain;

class KardexGetOneUseCase
{
    public function __construct(
        private readonly KardexRepositoryInterface $kardexRepository
    )
    {
    }

    public function __invoke(?string $warehouseId, ?string $productId, ?string $dateFrom, ?string $dateTo): array
    {
        return $this->kardexRepository->getOne($warehouseId, $productId, $dateFrom, $dateTo);
    }
}