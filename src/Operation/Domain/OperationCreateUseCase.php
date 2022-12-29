<?php

namespace App\Operation\Domain;

class OperationCreateUseCase
{
    private OperationRepositoryInterface $repository;

    public function __construct(OperationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Operation $operation): void
    {
        $this->repository->insert($operation);
    }
}
