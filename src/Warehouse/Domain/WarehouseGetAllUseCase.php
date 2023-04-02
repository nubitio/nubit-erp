<?php

namespace App\Warehouse\Domain;

use App\Core\Infrastructure\DataLoader\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

class WarehouseGetAllUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<Warehouse>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}