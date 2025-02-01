<?php

namespace App\Dashboard\Domain;

readonly class SalesWeeklyUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $localId): array
    {
        $data = $this->repository->getWeeklySales($localId);
        foreach ($data as $key => $value) {
            $data[$key]['venta'] = (float) $value['venta'];
        }

        return $data;
    }
}
