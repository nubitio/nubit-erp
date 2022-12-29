<?php

namespace App\Operation\Domain;

class OperationUpdateUseCase
{
    private OperationRepositoryInterface $repository;

    public function __construct(OperationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Operation $operation): void
    {
        $this->repository->update($operation);
    }
}
