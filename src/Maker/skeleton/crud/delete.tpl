<?php

namespace App\{{module}}\Domain;

class Delete{{module}}UseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke({{entityIdParam}}): void
    {
        $this->repository->delete($id);
    }
}