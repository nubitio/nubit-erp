<?php

namespace App\Operation\Domain;

class CreateOperationUseCase
{
    private OperationRepositoryInterface $repository;

    public function __construct(OperationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Operation $operation): Operation
    {
        return $this->repository->insert($operation);
    }
}
