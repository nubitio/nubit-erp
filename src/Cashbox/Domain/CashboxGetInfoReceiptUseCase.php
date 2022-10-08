<?php

namespace App\Cashbox\Domain;

class CashboxGetInfoReceiptUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): array
    {
        return $this->repository->getInfoByReceiptType($id);
    }
}