<?php

namespace App\Unit\Domain;

class UnitGetAllUseCase
{
    private UnitRepositoryInterface $repository;

    public function __construct(UnitRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}