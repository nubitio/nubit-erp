<?php

namespace App\Performance\Domain;

class DeletePerformanceUseCase
{
    private PerformanceRepositoryInterface $repository;

    public function __construct(PerformanceRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}