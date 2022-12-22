<?php

namespace App\Transfer\Domain;

class TransferUpdateUseCase
{
    private TransferRepositoryInterface $repository;

    public function __construct(TransferRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Transfer $transfer): void
    {
        $this->repository->update($transfer);
    }
}