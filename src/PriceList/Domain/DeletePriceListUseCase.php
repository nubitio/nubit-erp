<?php

namespace App\PriceList\Domain;

class DeletePriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): void
    {
        $this->repository->delete($id);
    }
}