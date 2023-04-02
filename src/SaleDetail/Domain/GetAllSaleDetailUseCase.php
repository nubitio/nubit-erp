<?php

namespace App\SaleDetail\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

class GetAllSaleDetailUseCase
{
    private SaleDetailRepositoryInterface $repository;

    public function __construct(SaleDetailRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<SaleDetail>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}