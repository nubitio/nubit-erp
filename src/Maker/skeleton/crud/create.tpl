<?php

namespace App\{{module}}\Domain;

readonly class Create{{module}}UseCase
{
    public function __construct(
        private {{module}}RepositoryInterface $repository
    )
    {
    }

    public function __invoke({{module}} ${{camelModule}}): {{module}}
    {
        return $this->repository->insert(${{camelModule}});
    }
}