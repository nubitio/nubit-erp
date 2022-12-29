<?php

namespace App\Transfer\Domain;

class TransferGetAllUseCase
{
    private TransferRepositoryInterface $repository;

    public function __construct(TransferRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?array $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}
