<?php

namespace App\Sale\Domain;

class GetOneSaleUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): Sale
    {
        return $this->repository->getOne($id);
    }
}