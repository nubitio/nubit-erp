<?php

namespace App\{{module}}\Domain;

class {{module}}CreateUseCase
{
    private {{module}}RepositoryInterface $repository;

    public function __construct({{module}}RepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke({{module}} ${{camelModule}}): {{module}}
    {
        return $this->repository->insert(${{camelModule}});
    }
}