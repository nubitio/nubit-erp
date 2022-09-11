<?php

namespace App\Unit\Domain;

class UnitDeleteUseCase
{
    private UnitRepositoryInterface $repository;

    public function __construct(UnitRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}