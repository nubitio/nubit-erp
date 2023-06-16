<?php

namespace App\Production\Domain;

class GetOneProductionUseCase
{
    private ProductionRepositoryInterface $repository;

    public function __construct(ProductionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Production
    {
        return $this->repository->getOne($id);
    }
}
