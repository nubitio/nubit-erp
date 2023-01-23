<?php

namespace App\{{module}}\Domain;

class Update{{module}}UseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke({{module}} ${{camelModule}}): {{module}}
    {
        return $this->repository->update(${{camelModule}});
    }
}