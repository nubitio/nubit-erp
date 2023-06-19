<?php

namespace App\UnitOfMeasure\Domain;

class CreateUnitOfMeasureUseCase
{
    private UnitOfMeasureRepositoryInterface $repository;

    public function __construct(UnitOfMeasureRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(UnitOfMeasure $unitOfMeasure): UnitOfMeasure
    {
        return $this->repository->insert($unitOfMeasure);
    }
}
