<?php

namespace App\Sale\Domain;

class SaleCreateUseCase
{
    public function __construct(
        private readonly SaleRepositoryInterface $repository,
    )
    {
    }

    public function __invoke(Sale $sale): void
    {
        $this->repository->insert($sale);
    }
}
