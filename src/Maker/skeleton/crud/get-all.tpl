<?php

namespace App\{{module}}\Domain;

class {{module}}GetAllUseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(?string $filter = null): array
    {
        return $this->repository->getAll($filter);
    }
}