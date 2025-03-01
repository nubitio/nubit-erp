<?php

namespace App\Sale\Domain;

class GetOneSaleUseCase
{
    public function __construct(private readonly SaleRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): Sale
    {
        return $this->repository->getOne($id);
    }
}
