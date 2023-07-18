<?php

namespace App\CashboxNewDetail\Domain;

class GetOneCashboxDetailUseCase
{
    private CashboxDetailRepositoryInterface $repository;

    public function __construct(CashboxDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?CashboxDetail
    {
        return $this->repository->getOne($id);
    }
}
