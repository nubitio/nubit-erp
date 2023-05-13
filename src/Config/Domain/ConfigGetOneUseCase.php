<?php

namespace App\Config\Domain;

class ConfigGetOneUseCase
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(int $id): ?Config
    {
        return $this->repository->getOne($id);
    }
}
