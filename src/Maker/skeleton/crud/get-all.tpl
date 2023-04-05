<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\Options;
use App\Core\Infrastructure\DataLoader\PaginatorInterface;

class GetAll{{module}}UseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Options $options
     * @return PaginatorInterface<{{module}}>
     */
    {
        return $this->repository->getAll($options): PaginatorInterface;
    }
}