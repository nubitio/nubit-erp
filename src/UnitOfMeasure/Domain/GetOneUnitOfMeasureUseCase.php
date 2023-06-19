<?php

namespace App\UnitOfMeasure\Domain;

class GetOneUnitOfMeasureUseCase
{
    private UnitOfMeasureRepositoryInterface $repository;

    public function __construct(UnitOfMeasureRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?UnitOfMeasure
    {
        return $this->repository->getOne($id);
    }
}
