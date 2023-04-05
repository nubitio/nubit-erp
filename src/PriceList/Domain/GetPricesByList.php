<?php

namespace App\PriceList\Domain;

readonly class GetPricesByList
{
    public function __construct(
        private PriceListRepositoryInterface $repository
    )
    {
    }

    /**
     * @return array<int, mixed>
     */
    public function __invoke(int $id): array
    {
        return $this->repository->getPricesByList($id);
    }
}