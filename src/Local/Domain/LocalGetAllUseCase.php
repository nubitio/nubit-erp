<?php

namespace App\Local\Domain;

class LocalGetAllUseCase
{
    private LocalRepositoryInterface $repository;

    public function __construct(LocalRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}