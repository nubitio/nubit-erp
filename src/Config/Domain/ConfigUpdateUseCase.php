<?php

namespace App\Config\Domain;

class ConfigUpdateUseCase
{
    public function __construct(private readonly ConfigRepositoryInterface $repository)
    {
    }

    public function __invoke(Config $config): Config
    {
        return $this->repository->update($config);
    }
}
