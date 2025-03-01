<?php

namespace App\Config\Domain;

class ConfigGetOneUseCase
{
    public function __construct(private readonly ConfigRepositoryInterface $repository)
    {
    }

    public function __invoke(int $id): ?Config
    {
        return $this->repository->getOne($id);
    }
}
