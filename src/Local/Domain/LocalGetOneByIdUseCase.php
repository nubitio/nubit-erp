<?php

namespace App\Local\Domain;

class LocalGetOneByIdUseCase
{
    public function __construct(
        private readonly LocalRepositoryInterface $repository
    ) {
    }

    public function __invoke(string $localId): Local
    {
        return $this->repository->getOneById($localId);
    }
}
