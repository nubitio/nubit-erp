<?php

namespace App\Production\Domain;

class CreateProductionUseCase
{
    private ProductionRepositoryInterface $repository;

    public function __construct(ProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Production $production): Production
    {
        return $this->repository->insert($production);
    }
}
