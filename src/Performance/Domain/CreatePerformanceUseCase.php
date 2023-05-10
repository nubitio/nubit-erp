<?php

namespace App\Performance\Domain;

class CreatePerformanceUseCase
{
    private PerformanceRepositoryInterface $repository;

    public function __construct(PerformanceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Performance $performance): Performance
    {
        return $this->repository->insert($performance);
    }
}