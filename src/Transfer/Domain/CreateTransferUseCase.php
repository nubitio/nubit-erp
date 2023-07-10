<?php

namespace App\Transfer\Domain;

class CreateTransferUseCase
{
    private TransferRepositoryInterface $repository;

    public function __construct(TransferRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Transfer $transfer): Transfer
    {
        return $this->repository->insert($transfer);
    }
}
