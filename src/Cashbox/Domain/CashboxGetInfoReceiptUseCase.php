<?php

namespace App\Cashbox\Domain;

class CashboxGetInfoReceiptUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param string $id
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(string $id): array
    {
        return $this->repository->getInfoByReceiptType($id);
    }
}
