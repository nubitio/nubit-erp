<?php

namespace App\Operation\Domain;

class OperationGetAllUseCase
{
    private OperationRepositoryInterface $repository;

    public function __construct(OperationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}