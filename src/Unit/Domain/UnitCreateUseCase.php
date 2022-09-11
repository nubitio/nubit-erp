<?php

namespace App\Unit\Domain;

class UnitCreateUseCase
{
    private UnitRepositoryInterface $repository;

    public function __construct(UnitRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Unit $unit): void
    {
        $this->repository->insert($unit);
    }
}