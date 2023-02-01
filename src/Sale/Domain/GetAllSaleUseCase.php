<?php

namespace App\Sale\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

class GetAllSaleUseCase
{
    private SaleRepositoryInterface $repository;

    public function __construct(SaleRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}