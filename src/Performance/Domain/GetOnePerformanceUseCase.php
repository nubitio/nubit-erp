<?php

namespace App\Performance\Domain;

class GetOnePerformanceUseCase
{
    private PerformanceRepositoryInterface $repository;

    public function __construct(PerformanceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Performance
    {
        return $this->repository->getOne($id);
    }
}