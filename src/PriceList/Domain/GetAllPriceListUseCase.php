<?php

namespace App\PriceList\Domain;

use App\Core\Domain\DataLoader\LoadOptions;
use App\Core\Domain\DataLoader\Paginator\PaginatorInterface;

class GetAllPriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return PaginatorInterface<PriceList>
     */
    public function __invoke(LoadOptions $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
