<?php

namespace App\PriceList\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

class GetAllPriceListUseCase
{
    private PriceListRepositoryInterface $repository;

    public function __construct(PriceListRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}