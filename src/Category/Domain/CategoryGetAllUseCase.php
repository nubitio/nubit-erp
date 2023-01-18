<?php

namespace App\Category\Domain;

use App\Core\Infrastructure\Common\DataLoader\Options;

class CategoryGetAllUseCase
{
    private CategoryRepositoryInterface $repository;

    public function __construct(CategoryRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): array
    {
        return $this->repository->getAll($options);
    }
}
