<?php

namespace App\{{module}}\Domain;

class {{module}}GetOneUseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(string $id): ?{{module}}
    {
        return $this->repository->getOne($id);
    }
}