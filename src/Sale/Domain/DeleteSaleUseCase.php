<?php

namespace App\Sale\Domain;

class DeleteSaleUseCase
{
    public function __construct(private readonly SaleRepositoryInterface $repository)
    {
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}
