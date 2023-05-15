<?php

namespace App\PriceList\Domain;

class CreatePriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(PriceList $priceList): PriceList
    {
        return $this->repository->insert($priceList);
    }
}
