<?php

namespace App\Config\Domain;

class ConfigUpdateUseCase
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Config $config): void
    {
        $this->repository->update($config);
    }
}
