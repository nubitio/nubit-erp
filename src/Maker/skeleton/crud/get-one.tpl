<?php

namespace App\{{module}}\Domain;

class GetOne{{module}}UseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke({{entityIdParam}}): ?{{module}}
    {
        return $this->repository->getOne($id);
    }
}