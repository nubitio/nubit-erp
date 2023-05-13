<?php

namespace App\Warehouse\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\Paginator\PaginatorInterface;

class WarehouseGetAllUseCase
{
    private WarehouseRepositoryInterface $repository;

    public function __construct(WarehouseRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return PaginatorInterface<Warehouse>
     */
    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
