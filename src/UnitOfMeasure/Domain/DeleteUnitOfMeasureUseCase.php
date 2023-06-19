<?php

namespace App\UnitOfMeasure\Domain;

class DeleteUnitOfMeasureUseCase
{
    private UnitOfMeasureRepositoryInterface $repository;

    public function __construct(UnitOfMeasureRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
