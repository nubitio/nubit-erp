<?php

namespace App\PriceList\Domain;

use App\PriceListDetail\Domain\PriceListDetail;

readonly class SavePriceList
{
    public function __construct(
        private PriceListRepositoryInterface $repository
    ) {
    }

    /**
     * @param PriceListDetail[] $prices
     */
    public function __invoke(array $prices): void
    {
        $this->repository->savePrices($prices);
    }
}
