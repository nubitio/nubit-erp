<?php

namespace App\Dashboard\Domain;

class ProductRotationUseCase
{
    public function __construct(
        private readonly DashboardRepositoryInterface $repository
    )
    {
    }

    public function __invoke(string $localId, string $lapse): array
    {
        return $this->repository->getProductRotation($localId, $lapse);
    }
}