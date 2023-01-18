<?php

namespace App\Category\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

class CategoryGetAllUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): DataStoreInterface
    {
        return $this->repository->getAll($options);
    }
}
