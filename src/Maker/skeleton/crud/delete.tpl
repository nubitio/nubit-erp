<?php

namespace App\{{module}}\Domain;

readonly class Delete{{module}}UseCase
{
    public function __construct(
        private {{module}}RepositoryInterface $repository
    )
    {
    }

    public function __invoke({{entityIdParam}}): void
    {
        $this->repository->delete($id);
    }
}