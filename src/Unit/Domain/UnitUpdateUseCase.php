<?php

namespace App\Unit\Domain;

class UnitUpdateUseCase
{
    private UnitRepositoryInterface $repository;

    public function __construct(UnitRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Unit $unit): void
    {
        $this->repository->update($unit);
    }
}