<?php

namespace App\PriceList\Domain;

class GetOnePriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?PriceList
    {
        return $this->repository->getOne($id);
    }
}
