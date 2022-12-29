<?php

namespace App\Cashbox\Domain;

class CashboxGetOneUseCase
{
    private CashboxRepositoryInterface $repository;

    public function __construct(CashboxRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): Cashbox
    {
        return $this->repository->getOne($id);
    }
}
