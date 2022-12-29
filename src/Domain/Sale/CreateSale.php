<?php

namespace App\Domain\Sale;

class CreateSale
{
    private ISaleRepository $saleRepository;

    public function __construct(ISaleRepository $saleRepository)
    {
        $this->saleRepository = $saleRepository;
    }

    public function execute(Sale $sale): void
    {
        $this->saleRepository->insert($sale);
    }
}
