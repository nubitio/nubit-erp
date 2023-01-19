<?php

namespace App\Config\Domain;

use App\Core\Infrastructure\DataLoader\DataStoreInterface;
use App\Core\Infrastructure\DataLoader\Options;

class ConfigGetAllUseCase
{
    private ConfigRepositoryInterface $repository;

    public function __construct(ConfigRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(Options $options): DataStoreInterface
    {
        return $this->repository->getAll($options);
    }
}