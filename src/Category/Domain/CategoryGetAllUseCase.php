<?php

namespace App\Category\Domain;

use ApiPlatform\State\Pagination\PaginatorInterface;
use App\Core\Infrastructure\DataLoader\Options;

class CategoryGetAllUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): PaginatorInterface
    {
        return $this->repository->getAll($options);
    }
}
