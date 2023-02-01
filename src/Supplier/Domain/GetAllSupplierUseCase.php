<?php

namespace App\Supplier\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

class GetAllSupplierUseCase
{
    private SupplierRepositoryInterface $repository;

    public function __construct(SupplierRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}