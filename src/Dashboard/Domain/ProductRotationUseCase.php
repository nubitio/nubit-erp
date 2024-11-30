<?php

namespace App\Dashboard\Domain;

readonly class ProductRotationUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(string $localId, string $lapse): array
    {
        return $this->repository->getProductRotation($localId, $lapse);
    }
}
