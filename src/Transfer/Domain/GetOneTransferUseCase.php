<?php

namespace App\Transfer\Domain;

class GetOneTransferUseCase
{
    private TransferRepositoryInterface $repository;

    public function __construct(TransferRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?Transfer
    {
        return $this->repository->getOne($id);
    }
}
