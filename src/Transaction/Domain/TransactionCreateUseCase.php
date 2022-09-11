<?php

namespace App\Transaction\Domain;

class TransactionCreateUseCase
{
    private TransactionRepositoryInterface $repository;

    public function __construct(TransactionRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Transaction $transaction): void
    {
        $this->repository->insert($transaction);
    }
}