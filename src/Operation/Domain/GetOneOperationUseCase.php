<?php

namespace App\Operation\Domain;

class GetOneOperationUseCase
{
    private OperationRepositoryInterface $repository;

    public function __construct(OperationRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Operation
    {
        return $this->repository->getOne($id);
    }
}