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
        $data = $this->repository->getDailySales($saleId);
        foreach ($data as $key => $value) {
            $data[$key]['venta'] = (float) $value['venta'];
        }

        return $data;
    }
}
