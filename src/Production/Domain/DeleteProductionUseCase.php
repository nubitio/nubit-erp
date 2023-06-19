<?php

namespace App\Production\Domain;

class DeleteProductionUseCase
{
    private ProductionRepositoryInterface $repository;

    public function __construct(ProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
