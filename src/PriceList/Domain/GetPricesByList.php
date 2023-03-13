<?php

namespace App\PriceList\Domain;

readonly class GetPricesByList
{
    public function __construct(
        private PriceListRepositoryInterface $repository
    )
    {
    }

    public function __invoke(int $id): array
    {
        return $this->repository->getPricesByList($id);
    }
}