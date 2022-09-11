<?php

namespace App\Transaction\Domain;

class TransactionGetAllUseCase
{
    private TransactionRepositoryInterface $repository;

    public function __construct(TransactionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}