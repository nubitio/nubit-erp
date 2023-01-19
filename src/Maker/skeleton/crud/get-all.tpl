<?php

namespace App\{{module}}\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

class {{module}}GetAllUseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): DataStoreInterface
    {
        return $this->repository->getAll($options);
    }
}