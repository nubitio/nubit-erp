<?php

namespace App\{{module}}\Domain;

readonly class Update{{module}}UseCase
{
    public function __construct(
        private {{module}}RepositoryInterface $repository
    )
    {
    }

    public function __invoke({{module}} ${{camelModule}}): {{module}}
    {
        return $this->repository->update(${{camelModule}});
    }
}