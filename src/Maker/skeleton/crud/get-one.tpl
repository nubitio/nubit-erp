<?php

namespace App\{{module}}\Domain;

readonly class GetOne{{module}}UseCase
{
    public function __construct(
        private {{module}}RepositoryInterface $repository
    )
    {
    }

    public function __invoke({{entityIdParam}}): ?{{module}}
    {
        return $this->repository->getOne($id);
    }
}