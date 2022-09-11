<?php

namespace App\Local\Domain;

class LocalGetAllUseCase
{
    private LocalRepositoryInterface $repository;

    public function __construct(LocalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}