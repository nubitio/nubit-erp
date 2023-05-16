<?php

namespace App\PriceList\Domain;

class DeletePriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): void
    {
        $this->repository->delete($id);
    }
}
